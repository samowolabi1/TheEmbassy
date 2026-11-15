<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * The help centre: step-by-step guides and common questions for the parts of the dashboard this
 * person can use. The content lives in config/help.php.
 */
class HelpController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $allowed = fn (array $item) => ! isset($item['area']) || $user->canManage($item['area']);

        return view('admin.help', [
            'guides' => collect(config('help.guides'))->filter($allowed)
                ->map(fn ($g) => [...$g, 'tour' => isset(config('help.tours')[$g['route']]) && Route::has($g['route'])]),
            'pages' => collect(config('help.pages'))->except('help')->filter($allowed)->filter(fn ($p) => ! empty($p['faqs'])),
        ]);
    }
}

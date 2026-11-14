<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Services\Content\Images;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Demo articles for trying the blog on a local copy: ten articles across every topic, by pastors
 * and members, with headings, lists and scripture; some with cover pictures; plus a draft and one
 * scheduled for next week. They sit alongside the design's starter articles.
 *
 *     php artisan db:seed --class=BlogDemoSeeder
 *
 * Safe to run again: articles already made are left alone. Refuses to run on a live site.
 */
class BlogDemoSeeder extends Seeder
{
    /** Cover colours by topic. */
    private const COLOURS = [
        'Faith' => ['#0a1d3f', '#18325f'],
        'Relationships' => ['#9d174d', '#ec4899'],
        'Career & money' => ['#14532d', '#16a34a'],
        'Campus life' => ['#1e3a8a', '#0ea5e9'],
        'Mental wellness' => ['#3b0764', '#7c3aed'],
        'Purpose' => ['#92400e', '#e0aa1e'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The blog demo is for local copies only, not a live site.');
        }

        $made = 0;
        foreach ($this->articles() as $a) {
            $slug = 'demo-'.Str::slug($a['title']);
            if (Post::where('slug', $slug)->exists()) {
                continue;
            }
            $made++;
            $post = new Post([
                'title' => $a['title'], 'slug' => $slug, 'topic' => $a['topic'], 'dek' => $a['dek'], 'body' => trim($a['body']),
                'swatch' => $a['swatch'] ?? 'navy', 'author_name' => $a['author'], 'author_bio' => $a['bio'],
                'is_featured' => false, 'meta_description' => $a['dek'],
            ]);
            if ($a['cover'] ?? false) {
                [$post->cover_path, $post->cover_thumb_path] = $this->cover($a['title'], $a['topic']);
                $post->cover_alt = $a['title'];
            }
            $post->published_at = $a['published'];
            $post->save();
        }

        $this->command?->info($made ? "Made {$made} demo articles." : 'The demo articles are already there.');
        $this->command?->line('See them at /blog, and the draft and scheduled article in admin › Blog.');
    }

    /**
     * A cover picture: the topic's colours, soft shapes and the title, saved like an uploaded
     * photo (resized, with a small copy for lists).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function cover(string $title, string $topic): array
    {
        if (! function_exists('imagettftext')) {
            return [null, null];
        }
        [$from, $to] = self::COLOURS[$topic] ?? self::COLOURS['Faith'];
        $w = 1600;
        $h = 1000;
        $img = imagecreatetruecolor($w, $h);
        $a = sscanf(ltrim($from, '#'), '%02x%02x%02x');
        $b = sscanf(ltrim($to, '#'), '%02x%02x%02x');
        for ($x = 0; $x < $w; $x++) {
            $t = $x / $w;
            imageline($img, $x, 0, $x, $h, imagecolorallocate($img, ...array_map(fn ($p, $q) => (int) ($p + ($q - $p) * $t), $a, $b)));
        }
        mt_srand(crc32($title));
        for ($i = 0; $i < 9; $i++) {
            $d = mt_rand(200, 760);
            imagefilledellipse($img, mt_rand(-100, $w + 100), mt_rand(-100, $h + 100), $d, $d, imagecolorallocatealpha($img, 255, 255, 255, mt_rand(108, 120)));
        }
        mt_srand();
        $font = resource_path('fonts/Poppins-Black.ttf');
        $label = resource_path('fonts/Poppins-SemiBold.ttf');
        imagettftext($img, 30, 0, 110, 200, imagecolorallocatealpha($img, 255, 255, 255, 25), $label, mb_strtoupper($topic));
        $y = 330;
        foreach (array_slice($this->wrap($title, $font, 78, 1380), 0, 4) as $line) {
            imagettftext($img, 78, 0, 104, $y, imagecolorallocate($img, 255, 255, 255), $font, $line);
            $y += 108;
        }
        imagefilledrectangle($img, 110, $y + 10, 290, $y + 22, imagecolorallocate($img, 224, 170, 30));

        $tmp = tempnam(sys_get_temp_dir(), 'cover').'.jpg';
        imagejpeg($img, $tmp, 88);
        try {
            $saved = app(Images::class)->store(new UploadedFile($tmp, 'cover.jpg', 'image/jpeg', null, true), 'posts', 1600, 640);

            return [$saved['path'], $saved['thumb_path']];
        } catch (Throwable) {
            return [null, null];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', $text) as $word) {
            $try = trim($line.' '.$word);
            $box = imagettfbbox($size, 0, $font, $try);
            if ($width < $box[2] - $box[0] && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }

        return [...$lines, $line];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        $pastor = ['author' => 'Pastor Femi Adeyemi', 'bio' => 'Pastor of The Royal Tribe Embassy. Loves the Word, long walks and jollof that is actually smoky.'];
        $grace = ['author' => 'Grace Nwosu', 'bio' => 'Leads Campus Tutors and the campus fellowship. Studying for a master\'s in public health.'];
        $david = ['author' => 'David Eze', 'bio' => 'Photographer and member of the media unit. Writes about work, money and faith.'];
        $ngozi = ['author' => 'Ngozi Eze', 'bio' => 'Head of the welfare unit, counsellor, and mum of two.'];

        return [
            [
                ...$pastor, 'topic' => 'Faith', 'swatch' => 'navy', 'cover' => true, 'published' => now()->subDays(3),
                'title' => 'When prayer feels like talking to the ceiling',
                'dek' => 'Every believer has seasons when heaven seems silent. Here is what to do while you wait.',
                'body' => <<<'MD'
If you have ever prayed and felt nothing, you are in good company. David wrote, "How long, Lord? Will you forget me forever?" (Psalm 13:1). Silence is not the same as absence.

## Silence is not rejection

God is not ignoring you because you did something wrong. Sometimes He is growing something in you that only grows in the quiet: patience, trust, and a faith that doesn't depend on feelings.

> "Be still, and know that I am God." Psalm 46:10

## What to do while you wait

- **Keep showing up.** Pray anyway, even if it's three sentences on the bus.
- **Pray Scripture.** When you don't have words, borrow the Psalms.
- **Don't wait alone.** Share it on the prayer wall or with your unit. The Tribe will carry you.
- **Write it down.** Keep a list. You'll be surprised how many answers you would have missed.

## He is closer than you think

The silence won't last forever. Keep knocking. The door you are standing at has a Father behind it.
MD,
            ],
            [
                ...$grace, 'topic' => 'Campus life', 'swatch' => 'mid', 'cover' => true, 'published' => now()->subDays(9),
                'title' => 'How to stay on fire for God in your final year',
                'dek' => 'Projects, exams and job hunting can squeeze God out of your calendar. It doesn\'t have to be that way.',
                'body' => <<<'MD'
Final year is intense. Between your project, exams and figuring out what's next, your quiet time is often the first thing to go. Here's what helped me.

## Make it small and daily

Fifteen focused minutes every morning beats a two-hour session once a month. Read one chapter, pray over your day, and write one thing you're grateful for.

## Study with purpose

Colossians 3:23 says to work at everything "as working for the Lord". Your project is not separate from your faith. Excellence is worship.

## Find your people

1. Join the campus fellowship on Thursdays.
2. Get an accountability partner in your department.
3. Come to the Campus Bible Study kick-off and bring a coursemate.

## Rest is holy too

Pulling all-nighters every week isn't a badge of honour. Sleep. God gave you a body to look after.

You can finish well academically *and* spiritually. Don't settle for one.
MD,
            ],
            [
                ...$david, 'topic' => 'Career & money', 'swatch' => 'gold', 'cover' => true, 'published' => now()->subDays(16),
                'title' => 'Budgeting on a small salary without losing your joy',
                'dek' => 'A simple plan for tithing, saving and still enjoying life on an entry-level income.',
                'body' => <<<'MD'
My first salary felt huge until the end of the month. If that's you, here's the simple system that changed things for me.

## Give first, save second, spend the rest

1. **Tithe and give** as soon as the salary lands. It reminds me where the money came from.
2. **Save 10–20%** into a separate account you don't carry a card for.
3. **Plan the rest:** rent, transport, data, food, and a little fun.

## Track for one month

Write down every naira for 30 days. You'll find the leaks quickly: the small chops after work, the unused subscriptions, the "small" transfers.

## Contentment is a skill

> "I have learned to be content whatever the circumstances." Philippians 4:11

Paul *learned* it. Comparison steals joy faster than any bill. Celebrate other people's wins, and keep running your own race.

## Grow your income too

Budgeting has a ceiling. Learn a skill, start something on the side, or open a shop on the Tribe Market. Wisdom with little prepares you for much.
MD,
            ],
            [
                ...$ngozi, 'topic' => 'Relationships', 'swatch' => 'wash', 'cover' => true, 'published' => now()->subDays(24),
                'title' => 'Five things to settle before you say "I do"',
                'dek' => 'Love is the foundation, but these conversations build the house.',
                'body' => <<<'MD'
Weddings get months of planning. Marriages deserve more. In counselling, these are the conversations I see couples skip most often.

## 1. Faith

Do you pray together? How will you raise your children in the faith? Being "both Christians" isn't the same as sharing a walk with God.

## 2. Money

Joint or separate accounts? How much do you give, save and send to family? Money fights are rarely about money.

## 3. Family expectations

Who visits, for how long, and who decides? Agree before the in-laws arrive.

## 4. Conflict

How do you each handle anger? Learn to fight fair: no silent treatment, no bringing up the past, no going to bed with a wall between you.

## 5. Dreams

Where do you each see yourselves in ten years? Make room for both.

If you're engaged, book a session with the pastors. It's the best wedding gift you can give yourselves.
MD,
            ],
            [
                ...$ngozi, 'topic' => 'Mental wellness', 'swatch' => 'ice', 'cover' => true, 'published' => now()->subDays(31),
                'title' => 'It\'s okay to not be okay: faith and anxiety',
                'dek' => 'Anxiety is not a lack of faith. Here is how to care for your mind and your soul.',
                'body' => <<<'MD'
Many young people in church struggle with anxiety quietly, afraid that admitting it means their faith is weak. Let's put that lie to rest.

## You are not alone

Elijah sat under a tree and asked God to take his life (1 Kings 19). God didn't rebuke him. He let him sleep, fed him, and spoke to him gently.

## Practical steps

- **Talk to someone.** A friend, a pastor, a counsellor. You can book a visit with a pastor from your account.
- **Look after your body.** Sleep, eat well, move every day, limit late-night scrolling.
- **Breathe and pray.** Slow breaths with a short prayer: *"Lord, You are with me."*
- **Get professional help when you need it.** Seeing a doctor or therapist is wisdom, not unbelief.

> "Cast all your anxiety on him because he cares for you." 1 Peter 5:7

## The church should be a safe place

If you're struggling, the welfare and prayer teams are here for you. You don't have to carry it alone.
MD,
            ],
            [
                ...$pastor, 'topic' => 'Purpose', 'swatch' => 'gold', 'cover' => false, 'published' => now()->subDays(38),
                'title' => 'Your gift was never meant to stay in the room',
                'dek' => 'What if your talent is the answer to someone else\'s prayer?',
                'body' => <<<'MD'
Every one of us has received a gift (1 Peter 4:10). Some sing, some design, some organise, some teach, some cook like heaven came down.

## Gifts are given to be given

Your gift isn't for applause. It's for service. The question isn't "How talented am I?" but "Who needs what I have?"

## Start where you are

- Join a unit. The choir, media, ushering, drama, prayer and welfare units all need you.
- Serve at the next outreach.
- Use your skill to bless someone this week, quietly.

## Faithful in little

Jesus said the one who is faithful with little will be trusted with much. Don't despise small beginnings. The stage comes after the service.
MD,
            ],
            [
                ...$david, 'topic' => 'Career & money', 'swatch' => 'navy', 'cover' => false, 'published' => now()->subDays(47),
                'title' => 'Side hustles that honour God (and pay the bills)',
                'dek' => 'Ideas for earning extra income with integrity, from members who are doing it.',
                'body' => <<<'MD'
More members are building businesses alongside their jobs and studies. Here are ideas from people in our own church.

## Ideas to try

1. **Food:** small chops, cakes, zobo, packed lunches for offices.
2. **Fashion and beauty:** tailoring, makeup, gele tying, hair.
3. **Skills:** tutoring, graphic design, photography, CV writing.
4. **Repairs:** phones, laptops, generators.

## Do it with integrity

- Price fairly and deliver what you promise.
- Don't let the hustle take your Sundays or your family time.
- Keep good records and pay what you owe.

## Get started on the Tribe Market

Open a shop on the Tribe Market and reach the whole church family. Share your listings to WhatsApp straight from your shop.
MD,
            ],
            [
                ...$grace, 'topic' => 'Campus life', 'swatch' => 'ice', 'cover' => false, 'published' => now()->subDays(56),
                'title' => 'Choosing friends who make you better',
                'dek' => 'The people closest to you shape who you become. Choose them with care.',
                'body' => <<<'MD'
"Walk with the wise and become wise" (Proverbs 13:20). Campus is where many lifelong friendships begin, so choose well.

## Look for these

- Friends who **pray with you**, not just party with you.
- Friends who **tell you the truth** in love.
- Friends who **celebrate your wins** without competing.

## Be that friend

The best way to find good friends is to become one. Check in on people. Show up when it's inconvenient. Keep confidences.

## Find them at church

Our house fellowships and units are full of people who want to grow. Join one this month.
MD,
            ],
            [
                ...$pastor, 'topic' => 'Faith', 'swatch' => 'mid', 'cover' => true, 'published' => now()->subDays(64),
                'title' => 'Why we declare the Word over our week',
                'dek' => 'The weekly declaration isn\'t positive thinking. It\'s agreeing with what God has already said.',
                'body' => <<<'MD'
Every Monday we share a declaration on the home page and in the weekly email. Some people ask: why say it out loud?

## Words carry weight

"The tongue has the power of life and death" (Proverbs 18:21). When we speak God's Word, we're not trying to make something true. We're agreeing with what is already true.

## How to use the declaration

- Say it out loud each morning.
- Tap **"I declare it"** on your account page.
- Share it with a friend who needs it.

## A faith that speaks

Jesus answered every temptation with "It is written." Let the Word be the first thing you say about your week.
MD,
            ],
            [
                ...$ngozi, 'topic' => 'Mental wellness', 'swatch' => 'wash', 'cover' => false, 'published' => now()->subDays(75),
                'title' => 'Rest is not laziness',
                'dek' => 'God built rest into creation. Here is how to take it without guilt.',
                'body' => <<<'MD'
In a culture that celebrates being busy, rest can feel like failure. But God rested on the seventh day, and He commands us to do the same.

## Signs you need rest

- You're tired even after sleeping.
- Small things make you snap.
- You can't remember the last time you laughed.

## How to rest well

1. Take one evening a week completely offline.
2. Do something that fills you up: a walk, music, cooking, time with friends.
3. Sleep before midnight at least four nights a week.

> "Come to me, all you who are weary and burdened, and I will give you rest." Matthew 11:28
MD,
            ],
            [
                ...$pastor, 'topic' => 'Purpose', 'swatch' => 'navy', 'cover' => false, 'published' => null,
                'title' => 'Draft: Lessons from Nehemiah for builders',
                'dek' => 'A draft on vision, opposition and finishing well. Not published yet.',
                'body' => "Still writing this one.\n\n## Vision\n\n## Opposition\n\n## Finishing well",
            ],
            [
                ...$david, 'topic' => 'Career & money', 'swatch' => 'gold', 'cover' => true, 'published' => now()->addDays(7)->setTime(8, 0),
                'title' => 'Preparing for your first job interview',
                'dek' => 'Scheduled for next week: what to research, what to wear, and how to answer "Tell me about yourself".',
                'body' => <<<'MD'
Your first interview can feel terrifying. Preparation turns fear into confidence.

## Before the day

- Research the company: what they do, their values, recent news.
- Prepare three stories that show your strengths.
- Plan your route and arrive 15 minutes early.

## On the day

- Dress one level above what the team wears.
- Pray, breathe, smile, and listen to each question fully.

## Afterwards

Send a short thank-you email within a day. Whatever happens, you've grown.
MD,
            ],
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\EmpowermentApplication;
use App\Models\EmpowermentProgramme;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A demo youth empowerment programme, open now, with six choices, a form and 24 applications
 * at different stages.
 *
 *     php artisan db:seed --class=EmpowermentDemoSeeder
 *
 * Safe to run again. Refuses to run on a live site.
 */
class EmpowermentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The empowerment demo is for local copies only, not a live site.');
        }
        if (EmpowermentProgramme::where('slug', 'youth-empowerment-demo')->exists()) {
            $this->command?->info('The demo programme is already there.');

            return;
        }

        $programme = EmpowermentProgramme::create([
            'slug' => 'youth-empowerment-demo',
            'title' => 'Youth Empowerment '.(now()->year + 1),
            'tagline' => 'Learn a skill. Start something. Build your future.',
            'intro' => "Twelve weeks of hands-on training for young people, free of charge, with mentors from our church family who run their own businesses.\n\nClasses hold on Saturdays from 10am to 2pm at the church, from February to April. Everyone who completes the programme gets a certificate and a starter kit, and the best business plans get a grant to start.\n\nApply below. We'll call shortlisted applicants for a short chat before places are confirmed.",
            'opens_on' => today()->subWeek(),
            'closes_on' => today()->addMonth(),
            'max_choices' => 2,
            'min_age' => 16,
            'max_age' => 35,
            'is_published' => true,
            'thank_you' => 'Shortlisted applicants will be called within two weeks of applications closing.',
        ]);
        $tracks = collect([
            ['Catering and baking', 'Cakes, small chops, pastries and running a food business from home.', 20],
            ['Fashion design', 'Pattern cutting, sewing and finishing, from Ankara shirts to bridal wear.', 15],
            ['Phone and laptop repair', 'Screens, batteries, software and setting up a repair stand.', 12],
            ['Photography and video', 'Shooting and editing events, portraits and products, and getting clients.', 10],
            ['Digital skills', 'Graphic design, social media management and freelancing online.', 25],
            ['Hair and makeup', 'Braiding, styling, everyday and bridal makeup.', 15],
        ])->map(fn ($t, $i) => $programme->tracks()->create(['name' => $t[0], 'description' => $t[1], 'slots' => $t[2], 'position' => $i]));

        $questions = collect([
            ['Why do you want to join, and what do you hope to do with what you learn?', 'textarea', null, true],
            ['Have you had any training in this before?', 'yes_no', null, true],
            ['What do you do at the moment?', 'radio', ['Student', 'Working', 'Running a business', 'Looking for work', 'Other'], true],
            ['Which days can you attend?', 'checkboxes', ['Saturday mornings', 'Weekday evenings', 'Sunday afternoons'], false],
            ['How did you hear about the programme?', 'select', ['Church announcement', 'A friend', 'WhatsApp', 'Instagram', 'Other'], false],
        ])->map(fn ($q, $i) => $programme->questions()->create(['label' => $q[0], 'type' => $q[1], 'options' => $q[2], 'is_required' => $q[3], 'position' => $i]));

        $people = ['Chioma Okeke', 'Tunde Adebayo', 'Blessing Udoh', 'Emeka Nwosu', 'Aisha Bello', 'David Okon', 'Grace Adeyemi', 'Samuel Ojo', 'Funmi Lawal', 'Kelechi Eze',
            'Halima Musa', 'Victor Ibe', 'Ruth Danjuma', 'Daniel Akpan', 'Esther Okafor', 'Ibrahim Sule', 'Joy Nnamdi', 'Peter Obi', 'Mary Alabi', 'Michael Etim',
            'Favour Chukwu', 'John Adeleke', 'Precious Akande', 'Seun Afolabi'];
        $areas = ['Ikeja', 'Yaba', 'Surulere', 'Lekki', 'Ajah', 'Ikorodu', 'Gbagada', 'Festac'];
        $whys = ['I want to start my own small business and support my family.', 'I already sell online and want to do it properly.', 'I finished school and want a skill I can earn from while I look for work.', 'I love this and want to turn it into a career.'];
        $statuses = ['new', 'new', 'new', 'new', 'new', 'new', 'new', 'new', 'shortlisted', 'shortlisted', 'shortlisted', 'shortlisted', 'accepted', 'accepted', 'accepted', 'accepted', 'accepted', 'accepted', 'waitlisted', 'waitlisted', 'declined', 'declined', 'new', 'new'];

        foreach ($people as $i => $name) {
            $first = $tracks[$i % $tracks->count()];
            $second = $tracks[($i + 2) % $tracks->count()];
            $a = new EmpowermentApplication([
                'name' => $name,
                'phone' => '0807 '.(100 + $i * 7).' '.(2000 + $i * 37),
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                'gender' => in_array(explode(' ', $name)[0], ['Chioma', 'Blessing', 'Aisha', 'Grace', 'Funmi', 'Halima', 'Ruth', 'Esther', 'Joy', 'Mary', 'Favour', 'Precious'], true) ? 'female' : 'male',
                'date_of_birth' => today()->subYears(17 + $i % 16)->subDays($i * 23),
                'location' => $areas[$i % count($areas)].', Lagos',
                'answers' => [
                    $questions[0]->key() => $whys[$i % count($whys)],
                    $questions[1]->key() => $i % 3 ? 'No' : 'Yes',
                    $questions[2]->key() => ['Student', 'Looking for work', 'Working', 'Running a business'][$i % 4],
                    $questions[3]->key() => $i % 2 ? ['Saturday mornings'] : ['Saturday mornings', 'Weekday evenings'],
                    $questions[4]->key() => ['Church announcement', 'A friend', 'WhatsApp', 'Instagram'][$i % 4],
                ],
            ]);
            $a->empowerment_programme_id = $programme->id;
            $a->status = $statuses[$i];
            $a->empowerment_track_id = in_array($statuses[$i], ['accepted'], true) ? $first->id : null;
            $a->decided_at = $statuses[$i] === 'new' ? null : now()->subDays(2);
            $a->created_at = now()->subDays(7 - intdiv($i, 4))->subHours($i);
            $a->save();
            $a->choices()->attach([$first->id => ['rank' => 1], $second->id => ['rank' => 2]]);
        }

        $this->command?->info('Made the demo programme with '.count($people).' applications: /empowerment');
    }
}

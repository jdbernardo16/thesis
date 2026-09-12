<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\Question;
use App\Models\Setting;
use App\Models\Stage;
use App\Models\Story;
use Illuminate\Database\Seeder;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        // Settings (spec coverage): star tiers + exp constants mirror
        // StarCalculator::forPct and ExpCalculator::forAttempt.
        Setting::updateOrCreate(['key' => 'stars.tiers'], ['value' => [
            'one_star_min' => 30,
            'two_star_min' => 60,
            'three_star_min' => 90,
        ]]);
        Setting::updateOrCreate(['key' => 'exp.per_correct'], ['value' => 10]);
        Setting::updateOrCreate(['key' => 'exp.bonus'], ['value' => [
            '0' => 0,
            '1' => 10,
            '2' => 30,
            '3' => 50,
        ]]);
        Setting::updateOrCreate(['key' => 'exp.repeat_perfect_divisor'], ['value' => 5]);

        $level = Level::updateOrCreate(
            ['order' => 1],
            [
                'title' => 'L1',
                'description' => 'Demo level: foundational bridging stories.',
                'cover_path' => null,
                'required_total_stars_to_unlock' => 0,
                'badge_name' => 'Starter',
                'is_published' => true,
            ]
        );

        // S1: text pretest, instructional, 5 MC.
        $s1 = Stage::updateOrCreate(
            ['level_id' => $level->id, 'order' => 1],
            [
                'title' => 'S1',
                'required_stars_to_unlock' => 0,
                'is_pretest' => true,
                'is_posttest' => false,
                'difficulty_tag' => 'instructional',
                'readability_note' => 'Short sentences, familiar words.',
                'estimated_minutes' => 10,
                'is_published' => true,
            ]
        );

        $story1 = Story::updateOrCreate(
            ['stage_id' => $s1->id],
            [
                'type' => 'text',
                'title' => 'Ang Alamat ng Parol',
                'body_html' => '<p>Si Lena ay gumawa ng parol kasama ang lola niya. Sinundan niya ang mga hakbang: gupitin ang papel, idikit ang kawayan, at isabit ang ilaw.</p><p>Nang matapos, kumislap ang parol. Naintindihan ni Lena na ang pagbabasa ay tulad ng paggawa ng parol — hakbang-hakbang.</p>',
                'cover_path' => null,
                'youtube_video_id' => null,
                'transcript' => null,
                'must_watch_pct' => 80,
            ]
        );

        $mc1 = [
            ['stem' => 'Sino ang kasama ni Lena sa paggawa ng parol?', 'options' => ['Lola', 'Guro', 'Kaibigan', 'Kapatid'], 'correct' => 'Lola'],
            ['stem' => 'Ano ang unang hakbang na ginawa nila?', 'options' => ['Gupitin ang papel', 'Isabit ang ilaw', 'Bumili ng kawayan', 'Kulayan ang dingding'], 'correct' => 'Gupitin ang papel'],
            ['stem' => 'Ano ang inihahalintulad sa pagbabasa?', 'options' => ['Paggawa ng parol', 'Pagluluto', 'Paglalaro', 'Pagtulog'], 'correct' => 'Paggawa ng parol'],
            ['stem' => 'Ano ang nangyari nang matapos ang parol?', 'options' => ['Kumislap ito', 'Nasira ito', 'Nawala ito', 'Nabasa ito'], 'correct' => 'Kumislap ito'],
            ['stem' => 'Ano ang aral ng kuwento?', 'options' => ['Hakbang-hakbang ang pagkatuto', 'Bilisan ang lahat', 'Huwag magbasa', 'Iwasan ang parol'], 'correct' => 'Hakbang-hakbang ang pagkatuto'],
        ];
        $this->syncQuestions($story1->id, array_map(fn ($q, $i) => [
            'order' => $i + 1,
            'type' => 'mc_single',
            'stem' => $q['stem'],
            'payload' => ['options' => $q['options'], 'correct_option_id' => $q['correct']],
            'explanation' => null,
        ], $mc1, array_keys($mc1)));

        // S2: youtube, 5 true/false.
        $s2 = Stage::updateOrCreate(
            ['level_id' => $level->id, 'order' => 2],
            [
                'title' => 'S2',
                'required_stars_to_unlock' => 1,
                'is_pretest' => false,
                'is_posttest' => false,
                'difficulty_tag' => 'instructional',
                'readability_note' => 'Video + transcript; literal comprehension.',
                'estimated_minutes' => 12,
                'is_published' => true,
            ]
        );

        $story2 = Story::updateOrCreate(
            ['stage_id' => $s2->id],
            [
                'type' => 'youtube',
                'title' => 'Kuwento sa Video: Ang Munting Ibon',
                'body_html' => null,
                'cover_path' => null,
                // Placeholder 11-char video id per spec.
                'youtube_video_id' => 'dQw4w9WgXcQ',
                'transcript' => 'Ang munting ibon na si Pip ay natutong lumipad. Una siyang nagmasid, pagkatapos ay nag-ensayo ng maliliit na pagaspas, at sa wakas ay lumipad nang mataas kasama ang kawan.',
                'must_watch_pct' => 80,
            ]
        );

        $tf = [
            ['stem' => 'Ang pangalan ng ibon ay Pip.', 'correct' => true],
            ['stem' => 'Natutong lumipad si Pip nang hindi nag-eensayo.', 'correct' => false],
            ['stem' => 'Nagmasid muna si Pip bago nag-ensayo.', 'correct' => true],
            ['stem' => 'Lumipad si Pip nang mag-isa at iniwan ang kawan.', 'correct' => false],
            ['stem' => 'Ang kuwento ay tungkol sa hakbang-hakbang na pagkatuto.', 'correct' => true],
        ];
        $this->syncQuestions($story2->id, array_map(fn ($q, $i) => [
            'order' => $i + 1,
            'type' => 'true_false',
            'stem' => $q['stem'],
            'payload' => ['correct' => $q['correct']],
            'explanation' => null,
        ], $tf, array_keys($tf)));

        // S3: text posttest, independent, mixed 6 Qs incl ordering + fill_blank.
        $s3 = Stage::updateOrCreate(
            ['level_id' => $level->id, 'order' => 3],
            [
                'title' => 'S3',
                'required_stars_to_unlock' => 1,
                'is_pretest' => false,
                'is_posttest' => true,
                'difficulty_tag' => 'independent',
                'readability_note' => 'Posttest: literal + sequencing + vocabulary.',
                'estimated_minutes' => 15,
                'is_published' => true,
            ]
        );

        $story3 = Story::updateOrCreate(
            ['stage_id' => $s3->id],
            [
                'type' => 'text',
                'title' => 'Ang Pista ng Pagbasa',
                'body_html' => '<p>Tuwing pista, nagbubukas ang mga bata ng aklat sa plaza. Una, pumipili sila ng kuwento. Ikalawa, binabasa nila ito nang malakas. Ikatlo, iginuguhit nila ang paboritong tagpo. Sa huli, ibinabahagi nila ang natutunan.</p><p>Ang salitang naglalarawan sa masayang pagdiriwang ay "pista". Ang pagbabasa ay nagbubuklod sa lahat.</p>',
                'cover_path' => null,
                'youtube_video_id' => null,
                'transcript' => null,
                'must_watch_pct' => 80,
            ]
        );

        $this->syncQuestions($story3->id, [
            [
                'order' => 1,
                'type' => 'mc_single',
                'stem' => 'Saan nagbubukas ng aklat ang mga bata?',
                'payload' => ['options' => ['Sa plaza', 'Sa ilog', 'Sa bundok', 'Sa palengke'], 'correct_option_id' => 'Sa plaza'],
                'explanation' => null,
            ],
            [
                'order' => 2,
                'type' => 'mc_single',
                'stem' => 'Ano ang ginagawa nila pagkatapos pumili ng kuwento?',
                'payload' => ['options' => ['Binabasa nang malakas', 'Natutulog', 'Umuuwi', 'Naglalaro'], 'correct_option_id' => 'Binabasa nang malakas'],
                'explanation' => null,
            ],
            [
                'order' => 3,
                'type' => 'true_false',
                'stem' => 'Iginuguhit ng mga bata ang paboritong tagpo.',
                'payload' => ['correct' => true],
                'explanation' => null,
            ],
            [
                'order' => 4,
                'type' => 'true_false',
                'stem' => 'Ang pagbabasa ay naghihiwalay sa mga bata.',
                'payload' => ['correct' => false],
                'explanation' => null,
            ],
            [
                'order' => 5,
                'type' => 'ordering',
                'stem' => 'Ayusin ang mga hakbang sa pista ayon sa kuwento.',
                'payload' => [
                    'items' => ['Ibahagi ang natutunan', 'Iguhit ang tagpo', 'Pumili ng kuwento', 'Basahin nang malakas'],
                    'correct_order' => ['Pumili ng kuwento', 'Basahin nang malakas', 'Iguhit ang tagpo', 'Ibahagi ang natutunan'],
                ],
                'explanation' => 'Sundin ang pagkakasunod sa talata.',
            ],
            [
                'order' => 6,
                'type' => 'fill_blank',
                'stem' => 'Ang salitang naglalarawan sa masayang pagdiriwang ay _____.',
                'payload' => ['acceptable_answers' => ['pista']],
                'explanation' => null,
            ],
        ]);
    }

    /**
     * @param  array<int, array{order:int,type:string,stem:string,payload:array,explanation:?string}>  $questions
     */
    protected function syncQuestions(int $storyId, array $questions): void
    {
        foreach ($questions as $q) {
            Question::updateOrCreate(
                ['story_id' => $storyId, 'order' => $q['order']],
                [
                    'type' => $q['type'],
                    'stem' => $q['stem'],
                    'payload' => $q['payload'],
                    'points' => 1,
                    'explanation' => $q['explanation'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}

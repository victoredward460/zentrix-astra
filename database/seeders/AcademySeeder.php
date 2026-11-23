<?php

namespace Database\Seeders;

use App\Models\AcademyCategory;
use App\Models\AcademyCourse;
use App\Models\AcademyLesson;
use App\Models\AcademyQuiz;
use App\Models\AcademyQuizQuestion;
use Illuminate\Database\Seeder;

class AcademySeeder extends Seeder
{
    public function run(): void
    {
        $curriculum = [
            [
                'category' => [
                    'name' => 'Everyday Security',
                    'slug' => 'everyday-security',
                    'description' => 'Practical habits for protecting personal accounts, devices, and information.',
                    'sort_order' => 10,
                ],
                'courses' => [
                    [
                        'title' => 'Cybersecurity Foundations',
                        'slug' => 'cybersecurity-foundations',
                        'summary' => 'Learn core security ideas and build simple, repeatable habits for daily life.',
                        'level' => 'beginner',
                        'estimated_minutes' => 25,
                        'sort_order' => 10,
                        'lessons' => [
                            [
                                'title' => 'The security basics',
                                'slug' => 'security-basics',
                                'summary' => 'Understand confidentiality, integrity, availability, and layered protection.',
                                'content' => "Security is the practice of reducing risk to information and the systems that use it. No single setting or product removes every risk; good protection combines sensible habits, reliable updates, and recovery plans.\n\nThree useful goals are:\n- Confidentiality: information is available only to people who should see it.\n- Integrity: information remains accurate and changes are authorized.\n- Availability: information and services can be used when needed.\n\nA practical starting point is to identify what matters, decide who needs access, use appropriate protections, and know how to recover if something goes wrong. Security is an ongoing process, not a one-time score.",
                                'sort_order' => 10,
                                'questions' => [
                                    [
                                        'prompt' => 'Which security goal focuses on preventing unauthorized people from viewing information?',
                                        'options' => ['a' => 'Availability', 'b' => 'Confidentiality', 'c' => 'Integrity', 'd' => 'Convenience'],
                                        'correct_option' => 'b',
                                        'explanation' => 'Confidentiality limits information access to people who are authorized to see it.',
                                    ],
                                    [
                                        'prompt' => 'Why is security best treated as an ongoing process?',
                                        'options' => ['a' => 'Risks and systems change over time', 'b' => 'One strong password protects every device', 'c' => 'Updates are never needed', 'd' => 'A single scan proves permanent safety'],
                                        'correct_option' => 'a',
                                        'explanation' => 'Systems, threats, and user needs change, so protections and recovery plans need periodic review.',
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Passwords and multi-factor authentication',
                                'slug' => 'passwords-and-mfa',
                                'summary' => 'Use unique passwords and a second sign-in factor to reduce account takeover risk.',
                                'content' => "A password reused across sites creates a chain reaction: if one service is breached, attackers may try the same password elsewhere. Use a unique password for each account. A reputable password manager can generate and store long, distinct passwords.\n\nMulti-factor authentication (MFA) asks for another proof in addition to a password. Prefer a passkey or security key where available; an authenticator app is generally stronger than a text message. MFA reduces risk but does not make an account invulnerable.\n\nNever approve an unexpected sign-in prompt. If you receive one, deny it, change the account password from the service's known official site or app, and review active sessions.",
                                'sort_order' => 20,
                                'questions' => [
                                    [
                                        'prompt' => 'What is the safest routine for passwords across different services?',
                                        'options' => ['a' => 'Reuse one memorable password', 'b' => 'Use unique passwords, stored in a password manager', 'c' => 'Share passwords with a trusted friend', 'd' => 'Change one character on every site'],
                                        'correct_option' => 'b',
                                        'explanation' => 'Unique passwords prevent a breach at one service from automatically exposing other accounts.',
                                    ],
                                    [
                                        'prompt' => 'What should you do with an unexpected MFA approval request?',
                                        'options' => ['a' => 'Approve it to clear the notification', 'b' => 'Ignore it indefinitely', 'c' => 'Deny it and investigate through the official service', 'd' => 'Send the code to the requester'],
                                        'correct_option' => 'c',
                                        'explanation' => 'An unexpected request may mean someone has your password. Deny it and secure the account through a known official channel.',
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Recognizing and reporting phishing',
                                'slug' => 'recognizing-phishing',
                                'summary' => 'Pause on unusual requests and verify them through a separate trusted channel.',
                                'content' => "Phishing messages try to persuade you to reveal information, send money, approve a sign-in, or open a risky attachment. They may use urgency, unexpected invoices, delivery notices, or familiar names. A polished message can still be fraudulent, and a spelling mistake alone is not reliable proof.\n\nCheck the actual sender and destination without relying on the display name. Do not use contact details or links supplied by a suspicious message. Instead, open the service using a saved bookmark or contact the person using a number you already trust.\n\nIf you interacted with a suspicious request, use the service's official recovery steps, change affected credentials, revoke unfamiliar sessions, and report the message to your organization's support or security contact.",
                                'sort_order' => 30,
                                'questions' => [
                                    [
                                        'prompt' => 'A message asks you to urgently approve a login you did not start. What is the best first action?',
                                        'options' => ['a' => 'Approve it so the alert stops', 'b' => 'Deny the request and verify using the official service', 'c' => 'Reply with your password', 'd' => 'Forward the sign-in code'],
                                        'correct_option' => 'b',
                                        'explanation' => 'Unexpected authentication requests should be denied and investigated through an independently trusted route.',
                                    ],
                                    [
                                        'prompt' => 'How should you check a surprising payment request from a colleague?',
                                        'options' => ['a' => 'Reply to the same message thread', 'b' => 'Use a separate, previously trusted contact method', 'c' => 'Click the payment link to inspect it', 'd' => 'Assume the display name proves identity'],
                                        'correct_option' => 'b',
                                        'explanation' => 'A separate trusted channel helps verify the request without relying on potentially compromised message details.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'category' => [
                    'name' => 'Web and Device Defense',
                    'slug' => 'web-and-device-defense',
                    'description' => 'Foundational guidance for using browsers, networks, and devices more safely.',
                    'sort_order' => 20,
                ],
                'courses' => [
                    [
                        'title' => 'Safer Web and Device Habits',
                        'slug' => 'safer-web-and-device-habits',
                        'summary' => 'Explore browser protections, updates, and safer connections in everyday use.',
                        'level' => 'beginner',
                        'estimated_minutes' => 20,
                        'sort_order' => 10,
                        'lessons' => [
                            [
                                'title' => 'Updates and device basics',
                                'slug' => 'updates-and-device-basics',
                                'summary' => 'Keep supported software current and prepare a reliable recovery path.',
                                'content' => "Software updates often fix security weaknesses as well as bugs. Enable updates from the device or application's built-in settings, and restart when required so the update can finish. Download software only from the vendor or a trusted app store.\n\nUse a screen lock and device encryption where available. Remove apps you no longer need, review app permissions, and make regular backups to a location protected from the device itself.\n\nA backup is useful only if it can be restored. Periodically check that important files are present and that you know how to recover access.",
                                'sort_order' => 10,
                                'questions' => [
                                    [
                                        'prompt' => 'What is a good way to obtain a software update?',
                                        'options' => ['a' => 'Use the built-in updater or vendor source', 'b' => 'Open an unexpected email attachment', 'c' => 'Disable device protections first', 'd' => 'Download from an unknown mirror'],
                                        'correct_option' => 'a',
                                        'explanation' => 'Built-in update tools and official vendor sources reduce the risk of installing modified software.',
                                    ],
                                    [
                                        'prompt' => 'How can you make a backup more useful?',
                                        'options' => ['a' => 'Keep it only on the same device', 'b' => 'Never check it', 'c' => 'Store it separately and test recovery periodically', 'd' => 'Share it publicly for convenience'],
                                        'correct_option' => 'c',
                                        'explanation' => 'A separate backup and a tested recovery process help when the original device is unavailable.',
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Safer browsing and connections',
                                'slug' => 'safer-browsing',
                                'summary' => 'Understand HTTPS indicators, public Wi-Fi limits, and careful link handling.',
                                'content' => "HTTPS encrypts traffic between your browser and a website and helps verify the connection endpoint. It does not prove that a website is honest, that its content is accurate, or that a page is free from harmful content. Check that the domain is the one you intended to visit.\n\nOn public Wi-Fi, prefer sites and apps that use current encryption, keep device sharing turned off when not needed, and avoid entering sensitive information into unexpected pages. A VPN may protect some traffic from a local network operator, but it does not make a destination trustworthy.\n\nWhen a link is unexpected, navigate to the service using a known bookmark or type its address yourself. Do not treat a padlock icon as a complete safety verdict.",
                                'sort_order' => 20,
                                'questions' => [
                                    [
                                        'prompt' => 'What does HTTPS primarily indicate?',
                                        'options' => ['a' => 'The website is always trustworthy', 'b' => 'The browser connection is encrypted to the addressed site', 'c' => 'The page contains no scams', 'd' => 'The site has been audited by your employer'],
                                        'correct_option' => 'b',
                                        'explanation' => 'HTTPS protects the connection and authenticates the domain certificate, but it does not establish that the site itself is trustworthy.',
                                    ],
                                    [
                                        'prompt' => 'How should you reach a service after receiving an unexpected login link?',
                                        'options' => ['a' => 'Use a saved bookmark or known official route', 'b' => 'Trust the visible sender name', 'c' => 'Enter credentials to see what happens', 'd' => 'Forward the link to coworkers'],
                                        'correct_option' => 'a',
                                        'explanation' => 'An independent route avoids relying on a potentially deceptive link.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($curriculum as $categoryData) {
            $category = AcademyCategory::query()->firstOrCreate(
                ['slug' => $categoryData['category']['slug']],
                [...$categoryData['category'], 'is_published' => true],
            );

            foreach ($categoryData['courses'] as $courseData) {
                $lessons = $courseData['lessons'];
                unset($courseData['lessons']);

                $course = AcademyCourse::query()->firstOrCreate(
                    ['slug' => $courseData['slug']],
                    [...$courseData, 'academy_category_id' => $category->id, 'is_published' => true],
                );

                foreach ($lessons as $lessonData) {
                    $questions = $lessonData['questions'];
                    unset($lessonData['questions']);

                    $lesson = AcademyLesson::query()->firstOrCreate(
                        ['academy_course_id' => $course->id, 'slug' => $lessonData['slug']],
                        [...$lessonData, 'is_published' => true],
                    );

                    $quiz = AcademyQuiz::query()->firstOrCreate(
                        ['academy_lesson_id' => $lesson->id],
                        [
                            'title' => 'Knowledge check',
                            'pass_percentage' => 70,
                            'is_published' => true,
                        ],
                    );

                    foreach ($questions as $index => $questionData) {
                        AcademyQuizQuestion::query()->firstOrCreate(
                            ['academy_quiz_id' => $quiz->id, 'sort_order' => ($index + 1) * 10],
                            [...$questionData, 'sort_order' => ($index + 1) * 10],
                        );
                    }
                }
            }
        }
    }
}

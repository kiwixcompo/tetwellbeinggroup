<?php
/**
 * Tet Wellbeing Group - AI Wellbeing Companion Portal (ai_companion.php)
 * 24/7 AI Coach, CBT Reframer, Animated Box Breathing, and Multilingual support.
 */

require_once 'db.php';
require_once 'EmotionalHealthService.php';

// Auth Guard: Client, Specialist & Admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'client';
$user_initial = strtoupper(substr($user_name, 0, 1));
$today_date = date('l, F j, Y');

// Multilingual translations dictionary
$translations = [
    'en' => [
        'title' => 'AI Wellbeing Companion',
        'subtitle' => 'Your 24/7 Digital Mental Health Coach',
        'welcome' => 'Hello ' . htmlspecialchars($user_name) . '. I am your AI Wellbeing Companion. I am here to help you practice CBT exercises, manage anxiety, or just listen. How are you feeling today?',
        'placeholder' => 'Type your message...',
        'send' => 'Send',
        'breathing_title' => 'Box Breathing Guide',
        'breathing_desc' => 'Relieve physical anxiety with a structured 4-4-4-4 cycle.',
        'reframer_title' => 'CBT Cognitive Reframer',
        'reframer_desc' => 'Identify negative thoughts and restructure them rationally.',
        'gratitude_title' => 'Gratitude Journal Prompt',
        'gratitude_desc' => 'Tap into positive psychology with daily reflection prompts.',
        'crisis_warning' => '🚨 Distress detected! Please seek clinical help or reach out to our emergency support. You are not alone.',
        'cbt_thought_lbl' => 'Automatic Negative Thought',
        'cbt_distortion_lbl' => 'Cognitive Distortion Type',
        'cbt_distortion_placeholder' => 'Select a distortion...',
        'cbt_reframing_lbl' => 'Rational Re-framed Thought',
        'cbt_reframe_btn' => 'Reframe Thought',
        'gratitude_btn' => 'Generate Positive Reflection Prompt',
        'gratitude_placeholder' => 'Click the button below to generate a positive prompt...'
    ],
    'ha' => [
        'title' => 'Abokin Jin Dadi na AI',
        'subtitle' => 'Kocin Lafiyar Kwakwalwa na Dijital na 24/7',
        'welcome' => 'Sannu ' . htmlspecialchars($user_name) . '. Ni ne Abokin Jin Dadi na AI. Ina nan don in taimake ka ka yi darussan CBT, sarrafa damuwa, ko kawai in saurare ka. Yaya kake ji yau?',
        'placeholder' => 'Rubuta saƙonka a nan...',
        'send' => 'Aika',
        'breathing_title' => 'Jagorar Numfashi na Akwati',
        'breathing_desc' => 'Sauƙaƙe damuwa ta jiki tare da tsarin zagayowar 4-4-4-4.',
        'reframer_title' => 'CBT Reframer na fahimi',
        'reframer_desc' => 'Gano munanan tunani kuma sake tsara su yadda ya kamata.',
        'gratitude_title' => 'Gwajin Jaridar Godiya',
        'gratitude_desc' => 'Taɓa cikin ingantaccen ilimin halin ɗan adam tare da saurin tunani.',
        'crisis_warning' => '🚨 An gano babban damuwa! Da fatan za a nemi taimakon asibiti ko tuntuɓi tallafin gaggawa namu. Ba kai kaɗai ba ne.',
        'cbt_thought_lbl' => 'Tunanin Kansa mara kyau',
        'cbt_distortion_lbl' => 'Nau\'in Gurɓacewar Fahimi',
        'cbt_distortion_placeholder' => 'Zaɓi gurɓacewar...',
        'cbt_reframing_lbl' => 'Tunani mai Kyau da Aka Sake Tsara shi',
        'cbt_reframe_btn' => 'Sake Tsara Tunani',
        'gratitude_btn' => 'Haifar da Hanzari na Godiya',
        'gratitude_placeholder' => 'Danna maɓallin da ke ƙasa don haifar da hanzari...'
    ],
    'yo' => [
        'title' => 'AI Alabaṣepọ alafia',
        'subtitle' => 'Olukọni Ilera Ọpọlọ 24/7 Rẹ',
        'welcome' => 'Pẹlẹ o ' . htmlspecialchars($user_name) . '. Emi ni AI Alabaṣepọ alafia rẹ. Mo wa nibi lati ran ọ lọwọ lati ṣe awọn adaṣe CBT, ṣakoso aibalẹ, tabi tẹtisi rẹ. Bawo ni ara rẹ loni?',
        'placeholder' => 'Kọ ifiranṣẹ rẹ sibi...',
        'send' => 'Firanṣẹ',
        'breathing_title' => 'Itọsọna Mimi Apoti',
        'breathing_desc' => 'Mu aibalẹ ti ara kuro pẹlu iyipo 4-4-4-4 ti a ṣeto.',
        'reframer_title' => 'CBT Reframer',
        'reframer_desc' => 'Ṣe idanimọ awọn ero odi ki o tun wọn ṣe ni ọgbọn.',
        'gratitude_title' => 'Iwe Iroyin Iupẹ',
        'gratitude_desc' => 'Tẹ sinu imọ-ọkan ti o dara pẹlu awọn afihan ojoojumọ.',
        'crisis_warning' => '🚨 A rii wahala nla! Jọwọ wa iranlọwọ ile-iwosan tabi kan si atilẹyin pajawiri wa. O ko nikan.',
        'cbt_thought_lbl' => 'Ero Odi Aifọwọyi',
        'cbt_distortion_lbl' => 'Iru Daru Oye ti Ero',
        'cbt_distortion_placeholder' => 'Yan iru daru oye...',
        'cbt_reframing_lbl' => 'Ero to Daju ti a Tun kọ',
        'cbt_reframe_btn' => 'Tun Ero Kọ',
        'gratitude_btn' => 'Ṣẹda Akọsilẹ Oore Ojoojumọ',
        'gratitude_placeholder' => 'Tẹ bọtini ti o wa ni isalẹ lati ṣẹda akọsilẹ...'
    ],
    'ig' => [
        'title' => 'AI Onye Enyemaka Ahụike',
        'subtitle' => 'Onye Ọzụzụ Ahụike Ọgụgụ Isi Dijitalụ Gị 24/7',
        'welcome' => 'Nnọọ ' . htmlspecialchars($user_name) . '. Abụ m AI Onye Enyemaka Ahụike gị. Anọ m ebe a iji nyere gị aka ime mmega ahụ CBT, jikwaa nchegbu, ma ọ bụ naanị gee gị ntị. Kedu ka ọ dị gị taa?',
        'placeholder' => 'Dee ozi gị ebe a...',
        'send' => 'Zipụ',
        'breathing_title' => 'Ntuziaka Nku ume igbe',
        'breathing_desc' => 'Belata nchegbu anụ ahụ site na usoro 4-4-4-4.',
        'reframer_title' => 'CBT Ndozigharị Echiche',
        'reframer_desc' => 'Chọpụta echiche ọjọọ ma gbanwee ha n\'ụzọ ezi uche dị na ya.',
        'gratitude_title' => 'Ihe edeturu Ekele',
        'gratitude_desc' => 'Kpatụ n\'ime nkà mmụta uche dị mma site n\'echiche ekele kwa ụbọchị.',
        'crisis_warning' => '🚨 Achọpụtara nnukwu nsogbu! Biko chọọ enyemaka ahụike ma ọ bụ kpọtụrụ nkwado mberede anyị. Ị nọghị naanị gị.',
        'cbt_thought_lbl' => 'Echiche Ọjọọ Na-apụta N\'onwe Ya',
        'cbt_distortion_lbl' => 'Ụdị Njegharị Echiche',
        'cbt_distortion_placeholder' => 'Họrọ ụdị njegharị...',
        'cbt_reframing_lbl' => 'Echiche Ezi Uche Emezigharịrị',
        'cbt_reframe_btn' => 'Gbanwee Echiche',
        'gratitude_btn' => 'Mepụta Ihe Ekele Ọhụrụ',
        'gratitude_placeholder' => 'Pịa bọtịnụ dị n\'okpuru ebe a...'
    ],
    'fr' => [
        'title' => 'Compagnon de Bien-être IA',
        'subtitle' => 'Votre Coach de Santé Mentale Digital 24/7',
        'welcome' => 'Bonjour ' . htmlspecialchars($user_name) . '. Je suis votre compagnon de bien-être IA. Je suis ici pour vous guider dans des exercices de TCC, gérer le stress, ou simplement vous écouter. Comment vous sentez-vous aujourd\'hui ?',
        'placeholder' => 'Écrivez votre message...',
        'send' => 'Envoyer',
        'breathing_title' => 'Respiration Carrée',
        'breathing_desc' => 'Réduisez le stress physique avec un cycle guidé 4-4-4-4.',
        'reframer_title' => 'Recadrage TCC',
        'reframer_desc' => 'Identifiez vos pensées négatives et restructurez-les de manière rationnelle.',
        'gratitude_title' => 'Journal de Gratitude',
        'gratitude_desc' => 'Pratiquez la psychologie positive avec des questions quotidiennes.',
        'crisis_warning' => '🚨 Détresse détectée ! Veuillez contacter un service d\'urgence ou notre support de crise. Vous n\'êtes pas seul.',
        'cbt_thought_lbl' => 'Pensée Négative Automatique',
        'cbt_distortion_lbl' => 'Type de Distorsion Cognitive',
        'cbt_distortion_placeholder' => 'Sélectionnez une distorsion...',
        'cbt_reframing_lbl' => 'Pensée Rationnelle Recadrée',
        'cbt_reframe_btn' => 'Recadrer la Pensée',
        'gratitude_btn' => 'Générer une Question Positive',
        'gratitude_placeholder' => 'Cliquez sur le bouton ci-dessous pour générer une question...'
    ],
    'ar' => [
        'title' => 'مساعد العافية الذكي',
        'subtitle' => 'مدرب الصحة النفسية الرقمي 24/7',
        'welcome' => 'مرحباً ' . htmlspecialchars($user_name) . '. أنا مساعد العافية الشخصي المدعوم بالذكاء الاصطناعي. أنا هنا لمساعدتك في تمارين العلاج المعرفي السلوكي، وإدارة القلق، أو مجرد الاستماع إليك. كيف تشعر اليوم؟',
        'placeholder' => 'اكتب رسالتك هنا...',
        'send' => 'إرسال',
        'breathing_title' => 'دليل تنفس الصندوق',
        'breathing_desc' => 'تخفيف القلق الجسدي من خلال دورة تنفس 4-4-4-4.',
        'reframer_title' => 'إعادة صياغة الأفكار (CBT)',
        'reframer_desc' => 'حدد الأفكار السلبية وأعد هيكلتها بشكل عقلاني.',
        'gratitude_title' => 'دفتر يوميات الامتنان',
        'gratitude_desc' => 'استثمر في علم النفس الإيجابي من خلال أسئلة التفكير اليومية.',
        'crisis_warning' => '🚨 تم اكتشاف علامات ضيق شديد! يرجى طلب المساعدة السريرية أو الاتصال بخدمة دعم الطوارئ لدينا. لست وحدك.',
        'cbt_thought_lbl' => 'الفكرة السلبية التلقائية',
        'cbt_distortion_lbl' => 'نوع التشوه المعرفي',
        'cbt_distortion_placeholder' => 'اختر التشوه المعرفي...',
        'cbt_reframing_lbl' => 'الفكرة العقلانية المعاد صياغتها',
        'cbt_reframe_btn' => 'إعادة صياغة الفكرة',
        'gratitude_btn' => 'إنشاء سؤال تفكير إيجابي',
        'gratitude_placeholder' => 'انقر على الزر أدناه لإنشاء سؤال...'
    ],
    'sw' => [
        'title' => 'Msaidizi wa Ustawi wa AI',
        'subtitle' => 'Kocha wako wa Afya ya Akili wa 24/7',
        'welcome' => 'Jambo ' . htmlspecialchars($user_name) . '. Mimi ni Msaidizi wako wa Ustawi wa AI. Niko hapa kukusaidia kufanya mazoezi ya CBT, kudhibiti wasiwasi, au kukusikiliza tu. Unajisikiaje leo?',
        'placeholder' => 'Andika ujumbe wako...',
        'send' => 'Tuma',
        'breathing_title' => 'Mwongozo wa Kupumua kwa Sanduku',
        'breathing_desc' => 'Punguza wasiwasi wa mwili kwa mzunguko uliopangwa wa 4-4-4-4.',
        'reframer_title' => 'Urekebishaji wa Mawazo wa CBT',
        'reframer_desc' => 'Tambua mawazo hasi na uyajenge upya kimantiki.',
        'gratitude_title' => 'Shajara ya Shukrani',
        'gratitude_desc' => 'Ingia katika saikolojia chanya na maswali ya kila siku ya kutafakari.',
        'crisis_warning' => '🚨 Hali ya dharura imegunduliwa! Tafadhali tafuta usaidizi wa matibabu au uwasiliane na usaidizi wetu wa dharura. Hauko peke yako.',
        'cbt_thought_lbl' => 'Wazo Hasi la Kiotomatiki',
        'cbt_distortion_lbl' => 'Aina ya Upotoshaji wa Utambuzi',
        'cbt_distortion_placeholder' => 'Chagua upotoshaji...',
        'cbt_reframing_lbl' => 'Wazo Jipya la Kimantiki',
        'cbt_reframe_btn' => 'Rekebisha Wazo',
        'gratitude_btn' => 'Tengeneza Swali la Shukrani',
        'gratitude_placeholder' => 'Bonyeza kitufe hapa chini ili kutengeneza swali...'
    ]
];

// Determine language selection
$lang = $_GET['lang'] ?? 'en';
if (!array_key_exists($lang, $translations)) {
    $lang = 'en';
}
$t = $translations[$lang];

// CBT and Gratitude Prompts dictionaries
$gratitude_prompts = [
    'en' => [
        "What is one small thing that went well today?",
        "Name a person you appreciate and write why.",
        "What is a personal strength you utilized this week?",
        "Describe a sensory experience (sight, sound, touch) that made you smile recently.",
        "Write about a hardship you overcame, and what you learned from it."
    ],
    'ha' => [
        "Wane abu ne ƙanƙani da ya faru da kyau yau?",
        "Wane mutum ne kake godiya da shi kuma rubuta dalili.",
        "Wace ƙarfin kanka kuka yi amfani da ita a wannan makon?",
        "Bayyana wani abu na musamman da ya sa ka yi murmushi kwanan nan.",
        "Rubuta game da wani wahala da kuka shawo kansa, da abin da kuka koya."
    ],
    'yo' => [
        "Kini nkan kekere kan ti o lọ daradara loni?",
        "Darukọ eniyan kan ti o mọ riri ki o kọ idi rẹ.",
        "Kini agbara ti o lo ni ọsẹ yii?",
        "Ṣe apejuwe nkan ti o rii tabi gbọ ti o mu ki o rẹrin musẹ laipẹ.",
        "Kọ nipa ipenija kan ti o bori, ati ohun ti o kọ."
    ],
    'ig' => [
        "Kedu obere ihe gara nke ọma taa?",
        "Kọọ aha otu onye ị nwere ekele maka ya na ihe kpatara ya.",
        "Kedu ike gị ị jiri rụọ ọrụ n'izu a?",
        "Kọwaa ihe ịchọpụtara ma ọ bụ nụrụ nke mere gị ọchị n'oge na-adịbeghị anya.",
        "Dee maka otu nsogbu ị meriri, na ihe ị mụtara na ya."
    ],
    'fr' => [
        "Quelle est une petite chose qui s'est bien passée aujourd'hui ?",
        "Nommez une personne que vous appréciez et écrivez pourquoi.",
        "Quelle est une force personnelle que vous avez utilisée cette semaine ?",
        "Décrivez une expérience sensorielle (vue, son, toucher) qui vous a fait sourire récemment.",
        "Écrivez sur une épreuve que vous avez surmontée et ce que vous en avez appris."
    ],
    'ar' => [
        "ما هو الشيء الصغير الذي سار بشكل جيد اليوم؟",
        "اذكر شخصاً تقدره واكتب السبب.",
        "ما هي القوة الشخصية التي استخدمتها هذا الأسبوع؟",
        "صِف تجربة حسية (رؤية، صوت، لمس) جعلتك تبتسم مؤخراً.",
        "اكتب عن صعوبة تغلبت عليها، وما تعلمته منها."
    ],
    'sw' => [
        "Ni jambo gani moja dogo lililoenda vizuri leo?",
        "Mtawaje mtu mmoja unayemthamini na uandike kwa nini.",
        "Ni nguvu gani binafsi uliyotumia wiki hii?",
        "Eleza uzoefu wa hisi (kuona, sauti, kugusa) uliokufanya utabasamu hivi karibuni.",
        "Andika kuhusu ugumu ulioshinda, na kile ulichojifunza kutoka kwake."
    ]
];

$ai_responses = [
    'en' => [
        'default' => "I hear you, and I'm here. It is completely okay to feel this way. Let's focus on taking a slow, deep breath, or we can look at breaking down what's on your mind using a CBT reframing exercise.",
        'anxious' => "Anxiety is a physical alarm system. Let's try the 'Box Breathing' exercise in the side panel together: Inhale for 4 seconds, hold for 4, exhale for 4, and hold for 4. Doing this 3 times resets your nervous system.",
        'sad' => "I understand you are feeling heavy right now. Remember, your thoughts are not permanent facts—they are temporary clouds passing by. Let's write down what is bringing you down so we can reframe it.",
        'stress' => "Caregiving and role strain are extremely heavy responsibilities. You cannot pour from an empty cup. Please consider planning a short respite break in the Caregiver Hub. For now, tell me: what is the most exhausting part of today?",
        'cbt' => "Excellent choice. Let's work on cognitive restructuring. In the side panel, fill out the Automatic Negative Thought and select a distortion. We will rewrite it into a healthier, objective perspective together.",
        'hi' => "Hello! I am your AI Companion. Feel free to talk to me in English, Hausa, Yoruba, Igbo, French, Arabic, or Swahili. How can I support you right now?"
    ],
    'ha' => [
        'default' => "Ina jinka, kuma ina nan. Yana da kyau a ji haka. Bari mu mai da hankali kan yin numfashi a hankali, ko kuma mu duba yadda za mu warware abin da ke damunka ta amfani da darasin CBT.",
        'anxious' => "Damuwa wani tsarin ƙararrawa ne na jiki. Bari mu gwada motsa jiki na 'Numfashi na Akwati' a cikin rukunin gefe tare: Numfasa na daƙiƙa 4, riƙe na 4, fitar da numfashi na 4, kuma riƙe na 4.",
        'sad' => "Na fahimci kana jin nauyi a yanzu. Ka tuna, tunaninka ba gaskiya ba ne na dindindin - gajimare ne na ɗan lokaci da ke wucewa. Bari mu rubuta abin da ke sanya ka cikin damuwa.",
        'stress' => "Kula da wani nauyi ne mai matukar nauyi. Don haka, don Allah a yi la'akari da tsara ɗan gajeren hutu a cikin Caregiver Hub. A yanzu, gaya mini: menene mafi gajiyawa a yau?",
        'cbt' => "Kyakkyawan zabi. Bari mu yi aiki a kan sake fasalin fahimi. A cikin rukunin gefe, cika Tunanin Kansa mara kyau kuma zaɓi gurɓacewa.",
        'hi' => "Sannu! Ni ne Abokin tafiyarku na AI. Jin kyauta don yin magana da ni. Yaya zan iya taimaka muku a yanzu?"
    ],
    'yo' => [
        'default' => "Mo gbọ rẹ, mo si wa nibi. O jẹ deede lati lero bẹ. Jẹ ki a mu mimi jinlẹ, tabi ki a lo adaṣe CBT lati tun awọn ero rẹ kọ.",
        'anxious' => "Aibalẹ jẹ bii itaniji fun ara. Jẹ ki a gbiyanju adaṣe 'Mimi Apoti' ni apa ibi: Mi sinu fun iṣẹju-aaya 4, di mọ fun 4, mi jade fun 4, ki o si di mọ fun 4.",
        'sad' => "Mo loye pe ara rẹ wuwo ni akoko yii. Ranti, awọn ero rẹ kọ n ṣe otitọ pipẹ—wọ́n dabi kurukuru kukuru ti n kọja lọ. Jẹ ki a kọ ohun to n yọ ọ lẹnu silẹ.",
        'stress' => "Itọju eniyan jẹ ẹru ti o wuwo pupọ. Jọwọ ronu lati gba isinmi diẹ ninu Caregiver Hub. Fun bayi, sọ fun mi: kini nkan ti o rẹ ọ julọ loni?",
        'cbt' => "Yiyan to dara. Jẹ ki a bẹrẹ adaṣe atunko ero. Ni apa ibi, kọ Ero Odi rẹ silẹ ki o yan iru daru oye rẹ.",
        'hi' => "Pẹlẹ o! Emi ni AI Alabaṣepọ rẹ. Kọ ifiranṣẹ rẹ si mi. Bawo ni mo ṣe le ran ọ lọwọ ni akoko yii?"
    ],
    'ig' => [
        'default' => "A na m anu ihe ị na-ekwu, m nọkwa ebe a. Ọ dị mma inwe mmetụta otu a. Ka anyị lekwasị anya n'iku ume nke ọma, ma ọ bụ anyị nwere ike iji mmega ahụ CBT dozie echiche gị.",
        'anxious' => "Nchegbu dị ka mkpu mkpọtụ anụ ahụ. Ka anyị nwaa mmega ahụ 'Nku ume igbe' ọnụ: Kupụ ume sekọnd 4, jide 4, kupụ ume 4, wee jide 4.",
        'sad' => "Aghọtara m na ọ na-esiri gị ike ugbu a. Cheta, echiche gị abụghị eziokwu na-adịgide adịgide. Ka anyị dee ihe na-ewute gị ka anyị wee gbanwee ya.",
        'stress' => "Ilekọta mmadụ bụ nnukwu ọrụ. Biko chepụta oge ezumike na Caregiver Hub. Maka ugbu a, gwa m: gịnị kacha esiri gị ike taa?",
        'cbt' => "Nhọrọ dị mma. Ka anyị rụọ ọrụ na ndozigharị echiche. Dejupụta echiche ọjọọ gị na kọlụm akụkụ ma họrọ njegharị echiche gị.",
        'hi' => "Nnọọ! M bụ AI Onye Enyemaka gị. Nweere onwe gị ịgwa m okwu. Kedu otu m ga-esi nyere gị aka ugbu a?"
    ],
    'fr' => [
        'default' => "Je vous écoute et je suis là. C'est tout à fait normal de ressentir cela. Concentrons-nous sur une respiration lente et profonde, ou analysons ce qui vous tracasse avec le recadrage TCC.",
        'anxious' => "L'anxiété est une alarme corporelle. Essayons la 'Respiration Carrée' dans le panneau latéral : Inspirez pendant 4s, retenez 4s, expirez 4s, retenez 4s.",
        'sad' => "Je comprends que vous vous sentiez triste. Rappelez-vous que vos pensées ne sont pas des faits absolus, mais des nuages temporaires. Écrivons-les pour les recadrer.",
        'stress' => "Le rôle de proche aidant est une lourde responsabilité. Vous devez aussi prendre soin de vous. Pensez à planifier un répit dans le Caregiver Hub. Racontez-moi : qu'est-ce qui vous fatigue le plus aujourd'hui ?",
        'cbt' => "Excellent choix. Travaillons sur la restructuration cognitive. Dans le volet latéral, saisissez votre Pensée Négative pour la transformer.",
        'hi' => "Bonjour ! Je suis votre compagnon IA. N'hésitez pas à me parler. Comment puis-je vous aider aujourd'hui ?"
    ],
    'ar' => [
        'default' => "أنا أسمعك وأنا هنا بجانبك. من الطبيعي تماماً أن تشعر بهذه الطريقة. دعنا نركز على أخذ نفس عميق وبطيء، أو يمكننا تفكيك ما يدور في ذهنك باستخدام تمرين العلاج المعرفي السلوكي.",
        'anxious' => "القلق هو جهاز إنذار جسدي. دعنا نجرب تمرين 'تنفس الصندوق' في اللوحة الجانبية معاً: شهيق لمدة 4 ثوانٍ، كتم النفس 4 ثوانٍ، زفير 4 ثوانٍ، ثم كتم النفس 4 ثوانٍ.",
        'sad' => "أنا أفهم أنك تشعر بالحزن والعبء الآن. تذكر أن أفكارك ليست حقائق دائمة، بل هي مجرد غيوم عابرة. دعنا نكتب ما يضايقك لنعيد صياغته.",
        'stress' => "رعاية الآخرين مسؤولية ثقيلة جداً. لا يمكنك العطاء من كأس فارغة. يرجى التفكير في التخطيط لاستراحة رعاية في Caregiver Hub. أخبرني الآن: ما هو الجزء الأكثر إرهاقاً اليوم؟",
        'cbt' => "اختيار ممتاز. دعنا نعمل على إعادة الهيكلة المعرفية. في اللوحة الجانبية، املأ الفكرة السلبية التلقائية واختر التشوه لنصححها معاً.",
        'hi' => "مرحباً! أنا مساعدك الذكي. لا تتردد في التحدث معي بأي لغة. كيف يمكنني دعمك الآن؟"
    ],
    'sw' => [
        'default' => "Nanakusikia, na niko hapa. Ni sawa kabisa kujisikia hivi. Hebu tuzingatie kuvuta pumzi polepole, au tunaweza kuangalia mawazo yako kwa kutumia CBT.",
        'anxious' => "Wasiwasi ni kingora cha mwili. Hebu tujaribu mazoezi ya 'Kupumua kwa Sanduku' kwenye paneli ya kando: Vuta pumzi kwa sekunde 4, zuia kwa 4, toa kwa 4, na zuia kwa 4.",
        'sad' => "Naelewa unajisikia vibaya sasa. Kumbuka, mawazo yako sio ukweli wa kudumu - ni mawingu ya muda tu yanayopita. Hebu tuandike yale yanayokusumbua ili tuyarekebishe.",
        'stress' => "Kutunza wengine ni jukumu zito sana. Unapaswa kujitunza pia. Fikiria kupanga mapumziko mafupi kwenye Caregiver Hub. Niambie: ni nini kinachokuchosha zaidi leo?",
        'cbt' => "Chaguo bora. Hebu tufanye kazi ya kurekebisha utambuzi. Kwenye paneli ya kando, jaza Wazo Hasi la Kiotomatiki na uchague upotoshaji.",
        'hi' => "Jambo! Mimi ni Msaidizi wako wa AI. Jisikie huru kuzungumza nami. Ninawezaje kukusaidia sasa hivi?"
    ]
];

// 2. HANDLE AJAX MESSAGE REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    header('Content-Type: application/json');
    $user_msg = trim($_POST['message'] ?? '');
    $selected_lang = $_POST['language'] ?? 'en';
    if (!array_key_exists($selected_lang, $translations)) {
        $selected_lang = 'en';
    }

    if (empty($user_msg)) {
        echo json_encode(['error' => 'Message cannot be empty']);
        exit;
    }

    // A. Check distress keywords (Safety Net)
    $is_distressed = EmotionalHealthService::checkDistress($user_msg);
    if ($is_distressed) {
        // Flag user crisis state in database/session
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET crisis_state = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
            } catch (PDOException $ex) {}
        }
        $_SESSION['crisis_state'] = 1;
        $user_email = $_SESSION['user_email'] ?? '';
        if (isset($_SESSION['mock_users'][$user_email])) {
            $_SESSION['mock_users'][$user_email]['crisis_state'] = 1;
        }
    }

    // B. Save User Message
    $saved_to_db = false;
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO ai_chat_logs (user_id, sender, message, language) VALUES (?, 'user', ?, ?)");
            $stmt->execute([$user_id, $user_msg, $selected_lang]);
            $saved_to_db = true;
        } catch (PDOException $ex) {}
    }
    if (!$saved_to_db) {
        $_SESSION['mock_ai_chats'][] = [
            'user_id' => $user_id,
            'sender' => 'user',
            'message' => $user_msg,
            'language' => $selected_lang,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    // C. Determine AI Response based on NLP Keywords
    $intent = 'default';
    $lowercase_msg = strtolower($user_msg);
    
    // Quick intent parser
    if ($is_distressed) {
        $intent = 'crisis';
    } elseif (preg_match('/(hello|hi|hey|sannu|jambo|bonjour|مرحبا)/i', $lowercase_msg)) {
        $intent = 'hi';
    } elseif (preg_match('/(anxious|anxiety|panic|fear|scared|worry|worrying|damuwa|aibalẹ|nchegbu|angoisse|قلق|wasiwasi)/i', $lowercase_msg)) {
        $intent = 'anxious';
    } elseif (preg_match('/(sad|depressed|crying|hopeless|lonely|down|wute|banin maraya|banin ciki|ibanujẹ|obi mgbawa|triste|حزين|huzuni)/i', $lowercase_msg)) {
        $intent = 'sad';
    } elseif (preg_match('/(stress|exhausted|tired|burnout|strain|overwhelmed|gajiya|aare|ike ọgwụgwụ|fatigue|تعب|إرhaق|choka)/i', $lowercase_msg)) {
        $intent = 'stress';
    } elseif (preg_match('/(cbt|reframe|distortion|thought|tunanin|ero odi|echiche ọjọọ|recadrage|صياغة|upotoshaji)/i', $lowercase_msg)) {
        $intent = 'cbt';
    }

    // Select response based on intent and language
    if ($intent === 'crisis') {
        $ai_msg = $translations[$selected_lang]['crisis_warning'];
    } else {
        $ai_msg = $ai_responses[$selected_lang][$intent] ?? $ai_responses[$selected_lang]['default'];
    }

    // D. Save AI Response
    $saved_ai_to_db = false;
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO ai_chat_logs (user_id, sender, message, language) VALUES (?, 'ai', ?, ?)");
            $stmt->execute([$user_id, $ai_msg, $selected_lang]);
            $saved_ai_to_db = true;
        } catch (PDOException $ex) {}
    }
    if (!$saved_ai_to_db) {
        $_SESSION['mock_ai_chats'][] = [
            'user_id' => $user_id,
            'sender' => 'ai',
            'message' => $ai_msg,
            'language' => $selected_lang,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    // E. Return JSON Response
    echo json_encode([
        'status' => 'success',
        'user_message' => $user_msg,
        'ai_message' => $ai_msg,
        'is_crisis' => $is_distressed
    ]);
    exit;
}

// 3. FETCH HISTORICAL CHAT LOGS
$chats = [];
$loaded_from_db = false;
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ai_chat_logs WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->execute([$user_id]);
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $loaded_from_db = true;
    } catch (PDOException $ex) {}
}
if (!$loaded_from_db && isset($_SESSION['mock_ai_chats'])) {
    $chats = array_filter($_SESSION['mock_ai_chats'], function($c) use ($user_id) {
        return $c['user_id'] == $user_id;
    });
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <title><?php echo $t['title']; ?> - Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#F7F5F0',       // Soft warm off-white
                            sage: '#5E8C71',     // Calming Sage Green
                            slate: '#264653',    // Deep Slate
                            sky: '#8ECAE6',      // Soft Sky Blue
                            coral: '#E76F51',    // Muted Coral
                            sageHover: '#4D755D',
                            coralHover: '#D95C3D',
                            cardBg: '#FFFFFF',
                            inputBg: '#FAF9F6',
                            sageLight: '#E8EFEA',
                            coralLight: '#FCEBE6'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif']
                    },
                    borderRadius: {
                        '2xl': '1rem',
                        '3xl': '1.5rem'
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(94, 140, 113, 0.08)',
                        'card': '0 10px 30px -5px rgba(38, 70, 83, 0.04)',
                        'active': '0 12px 24px -6px rgba(94, 140, 113, 0.15)'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #F7F5F0;
            color: #264653;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .breathing-circle {
            transition: transform 4s linear;
        }
        .breathing-inhale {
            transform: scale(2.0);
            background-color: #5E8C71;
        }
        .breathing-hold-inhale {
            transform: scale(2.0);
            background-color: #8ECAE6;
        }
        .breathing-exhale {
            transform: scale(1.0);
            background-color: #E76F51;
        }
        .breathing-hold-exhale {
            transform: scale(1.0);
            background-color: #264653;
        }
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #D9D5CB;
            border-radius: 9999px;
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
            </a>

            <!-- Actions -->
            <div class="flex items-center gap-4">
                <!-- Emergency Support Button -->
                <button type="button" onclick="openEmergencyModal()" class="flex items-center gap-2 rounded-2xl bg-brand-coral px-4 py-2 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:bg-brand-coralHover hover:shadow-lg active:scale-95">
                    <svg class="h-4.5 w-4.5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span>Emergency Support</span>
                </button>

                <!-- Profile Avatar -->
                <div class="relative group cursor-pointer">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-brand-sage/20 bg-brand-sageLight text-sm font-bold text-brand-sage transition-all hover:border-brand-sage">
                        <?php echo $user_initial; ?>
                    </div>
                    <div class="absolute right-0 mt-2 w-48 origin-top-right rounded-2xl bg-white p-2 shadow-xl border border-gray-100 opacity-0 scale-95 pointer-events-none transition-all duration-200 group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto">
                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Signed in as</div>
                        <div class="px-3 py-1 font-bold text-brand-slate text-sm truncate"><?php echo htmlspecialchars($user_name); ?></div>
                        <hr class="my-2 border-gray-100">
                        <?php if ($user_role === 'admin'): ?>
                        <a href="admin_dashboard.php" class="block px-3 py-2 text-sm text-brand-sage rounded-xl hover:bg-brand-sageLight transition-colors font-bold">Admin Console</a>
                        <?php endif; ?>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">My Profile</a>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">Settings</a>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-medium font-outfit">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="flex-grow mx-auto w-full max-w-6xl px-4 py-8 pb-24 md:pb-8 fade-in">
        
        <!-- Header Banner -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-white border border-[#EBE8E0] shadow-soft p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-2 z-10 max-w-2xl">
                <p class="text-xs font-bold tracking-wider text-brand-sage uppercase font-outfit"><?php echo $today_date; ?></p>
                <h1 class="text-3xl font-extrabold font-outfit text-brand-slate tracking-tight mt-1">
                    <?php echo $t['title']; ?>
                </h1>
                <p class="text-gray-500 text-sm leading-relaxed">
                    <?php echo $t['subtitle']; ?>. Switch languages dynamically to chat or access CBT-based stress relievers in your preferred language.
                </p>
            </div>
            
            <!-- Language Selector Dropdown -->
            <div class="relative shrink-0 w-full md:w-auto">
                <label for="lang-selector" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Preferred Language</label>
                <select id="lang-selector" onchange="switchLanguage(this.value)" class="w-full md:w-48 px-4 py-2.5 rounded-xl border border-gray-200 bg-brand-inputBg text-brand-slate font-semibold text-sm focus:border-brand-sage focus:ring-2 focus:ring-brand-sage/20 focus:outline-none transition-all cursor-pointer">
                    <option value="en" <?php if ($lang === 'en') echo 'selected'; ?>>English</option>
                    <option value="ha" <?php if ($lang === 'ha') echo 'selected'; ?>>Hausa (Harshen Hausa)</option>
                    <option value="yo" <?php if ($lang === 'yo') echo 'selected'; ?>>Yoruba (Èdè Yorùbá)</option>
                    <option value="ig" <?php if ($lang === 'ig') echo 'selected'; ?>>Igbo (Asụsụ Igbo)</option>
                    <option value="fr" <?php if ($lang === 'fr') echo 'selected'; ?>>Français</option>
                    <option value="ar" <?php if ($lang === 'ar') echo 'selected'; ?>>العربية (Arabic)</option>
                    <option value="sw" <?php if ($lang === 'sw') echo 'selected'; ?>>Kiswahili (Swahili)</option>
                </select>
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <?php include 'nav_menu.php'; ?>

        <!-- GRID LAYOUT -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left & Middle: Chat Pane (Col-span 2) -->
            <div class="lg:col-span-2 flex flex-col h-[600px] bg-white rounded-3xl border border-[#EBE8E0] shadow-soft overflow-hidden">
                <!-- Chat Header -->
                <div class="p-4 border-b border-[#EBE8E0] bg-brand-sageLight/30 flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-brand-sage/10 text-brand-sage text-lg">
                        🤖
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-brand-slate">Wellbeing Companion (AI)</h3>
                        <div class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Online & Listening</span>
                        </div>
                    </div>
                </div>

                <!-- Messages Window -->
                <div id="messages-container" class="flex-grow p-6 overflow-y-auto space-y-4">
                    
                    <!-- AI Initial Welcome Message -->
                    <div class="flex items-start gap-3 max-w-[85%]">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-brand-sageLight text-brand-sage text-xs">
                            🤖
                        </div>
                        <div class="rounded-2xl rounded-tl-none bg-brand-bg px-4 py-3 text-sm text-brand-slate leading-relaxed shadow-sm">
                            <?php echo $t['welcome']; ?>
                        </div>
                    </div>

                    <!-- Historical messages -->
                    <?php foreach ($chats as $c): ?>
                        <?php if ($c['sender'] === 'user'): ?>
                            <div class="flex items-start gap-3 max-w-[85%] ml-auto justify-end">
                                <div class="rounded-2xl rounded-tr-none bg-brand-sage text-white px-4 py-3 text-sm leading-relaxed shadow-sm">
                                    <?php echo htmlspecialchars($c['message']); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex items-start gap-3 max-w-[85%]">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-brand-sageLight text-brand-sage text-xs">
                                    🤖
                                </div>
                                <div class="rounded-2xl rounded-tl-none bg-brand-bg px-4 py-3 text-sm text-brand-slate leading-relaxed shadow-sm">
                                    <?php echo htmlspecialchars($c['message']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Typing indicator -->
                <div id="typing-indicator" class="px-6 py-2 flex items-center gap-2 text-gray-400 text-xs hidden">
                    <span class="font-semibold">AI is typing</span>
                    <span class="flex gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 300ms"></span>
                    </span>
                </div>

                <!-- Input Footer -->
                <div class="p-4 border-t border-[#EBE8E0] bg-white">
                    <form id="chat-form" onsubmit="sendMessage(event)" class="flex gap-3">
                        <button type="button" id="mic-btn" onclick="toggleSpeech()" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 hover:border-brand-sage hover:bg-brand-sageLight/30 text-gray-400 hover:text-brand-sage transition-all active:scale-95">
                            <svg id="mic-icon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                        </button>
                        <input type="text" id="chat-input" placeholder="<?php echo $t['placeholder']; ?>" autocomplete="off" class="flex-grow rounded-xl border border-gray-200 bg-brand-inputBg px-4 py-2.5 text-sm text-brand-slate focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/20 transition-all">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition-all active:scale-95">
                            <?php echo $t['send']; ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Sidebar: CBT & Coping Toolbox (Col-span 1) -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Box Breathing Widget -->
                <section class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0]">
                    <div class="mb-4">
                        <h3 class="text-md font-bold font-outfit text-brand-slate flex items-center gap-2">
                            <span>🧘</span> <?php echo $t['breathing_title']; ?>
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5"><?php echo $t['breathing_desc']; ?></p>
                    </div>

                    <!-- Animation Area -->
                    <div class="my-6 flex flex-col items-center justify-center text-center">
                        <div class="relative h-32 w-32 flex items-center justify-center">
                            <!-- Outermost guide -->
                            <div class="absolute h-full w-full rounded-full border border-dashed border-gray-200"></div>
                            <!-- Shrinking/expanding circle -->
                            <div id="breathing-circle" class="h-16 w-16 rounded-full bg-brand-sage opacity-75 breathing-circle flex items-center justify-center text-white text-xs font-bold font-outfit shadow-soft"></div>
                        </div>
                        <div class="mt-4">
                            <span id="breathing-state" class="text-sm font-bold text-brand-slate uppercase tracking-wider font-outfit">Idle</span>
                            <span id="breathing-timer" class="text-xs text-gray-400 font-bold block mt-1">Click Start to Breathe</span>
                        </div>
                    </div>

                    <button type="button" id="breathing-btn" onclick="toggleBreathing()" class="w-full py-2.5 rounded-2xl border-2 border-brand-sage hover:bg-brand-sageLight/50 text-brand-sage font-bold text-xs transition-all active:scale-95">
                        Start Box Breathing Cycle
                    </button>
                </section>

                <!-- CBT Cognitive Reframer Widget -->
                <section class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0]">
                    <div class="mb-4">
                        <h3 class="text-md font-bold font-outfit text-brand-slate flex items-center gap-2">
                            <span>🧠</span> <?php echo $t['reframer_title']; ?>
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5"><?php echo $t['reframer_desc']; ?></p>
                    </div>

                    <form id="reframer-form" onsubmit="reframeThought(event)" class="space-y-4">
                        <div>
                            <label for="cbt-thought" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1"><?php echo $t['cbt_thought_lbl']; ?></label>
                            <textarea id="cbt-thought" rows="2" class="w-full text-xs rounded-xl border border-gray-200 bg-brand-inputBg p-3 text-brand-slate focus:border-brand-sage focus:outline-none" placeholder="e.g. 'I am failing my family as a caregiver...'"></textarea>
                        </div>

                        <div>
                            <label for="cbt-distortion" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1"><?php echo $t['cbt_distortion_lbl']; ?></label>
                            <select id="cbt-distortion" class="w-full text-xs rounded-xl border border-gray-200 bg-brand-inputBg px-3 py-2 text-brand-slate focus:border-brand-sage focus:outline-none">
                                <option value="" disabled selected><?php echo $t['cbt_distortion_placeholder']; ?></option>
                                <option value="catastrophizing">Catastrophizing (Expecting the absolute worst)</option>
                                <option value="all-or-nothing">All-or-Nothing (Black and white thinking)</option>
                                <option value="mind-reading">Mind Reading (Assuming others judge you)</option>
                                <option value="emotional-reasoning">Emotional Reasoning (Feeling bad = being bad)</option>
                                <option value="personalization">Personalization (Blaming yourself for everything)</option>
                            </select>
                        </div>

                        <div id="cbt-result-box" class="p-3 bg-brand-sageLight/50 border border-brand-sage/10 rounded-xl hidden text-xs leading-relaxed text-brand-slate">
                            <strong class="block text-[9px] uppercase text-brand-sage tracking-wider mb-1"><?php echo $t['cbt_reframing_lbl']; ?></strong>
                            <p id="cbt-reframed-text"></p>
                        </div>

                        <button type="submit" class="w-full py-2.5 rounded-2xl bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-xs shadow-md transition-all active:scale-95">
                            <?php echo $t['cbt_reframe_btn']; ?>
                        </button>
                    </form>
                </section>

                <!-- Gratitude Prompts Widget -->
                <section class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0]">
                    <div class="mb-4">
                        <h3 class="text-md font-bold font-outfit text-brand-slate flex items-center gap-2">
                            <span>🌱</span> <?php echo $t['gratitude_title']; ?>
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5"><?php echo $t['gratitude_desc']; ?></p>
                    </div>

                    <div class="p-4 bg-brand-inputBg border border-gray-150 rounded-2xl text-xs text-gray-500 leading-relaxed text-center italic min-h-[70px] flex items-center justify-center">
                        <p id="gratitude-text"><?php echo $t['gratitude_placeholder']; ?></p>
                    </div>

                    <button type="button" onclick="generateGratitudePrompt()" class="w-full mt-4 py-2.5 rounded-2xl border-2 border-brand-sage hover:bg-brand-sageLight/50 text-brand-sage font-bold text-xs transition-all active:scale-95">
                        <?php echo $t['gratitude_btn']; ?>
                    </button>
                </section>

            </div>
        </div>

    </main>

    <!-- EMERGENCY SUPPORT MODAL -->
    <div id="emergency-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeEmergencyModal()"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/20 transform scale-95 transition-all duration-300">
            <button onclick="closeEmergencyModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <div class="flex items-center gap-3 mb-4 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-xs text-brand-coral font-semibold">Immediate 24/7 Assistance</p>
                </div>
            </div>

            <p class="text-sm text-gray-600 mb-6 leading-relaxed">If you are facing an emergency, in distress, or in danger of hurting yourself, please reach out to one of the free support services below.</p>

            <div class="space-y-3.5 mb-6">
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">988 Crisis Lifeline</h4>
                        <p class="text-xs text-gray-500">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 988</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Samaritans Helpline</h4>
                        <p class="text-xs text-gray-500">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 116 123</a>
                </div>
            </div>

            <button onclick="closeEmergencyModal()" class="w-full py-2.5 rounded-2xl border-2 border-gray-200 hover:bg-gray-50 text-gray-500 font-bold text-xs transition-colors">
                Close support panel
            </button>
        </div>
    </div>

    <!-- SCRIPT LOGIC -->
    <script>
        const currentLang = "<?php echo $lang; ?>";

        // Dynamic language switching
        function switchLanguage(newLang) {
            window.location.href = `ai_companion.php?lang=${newLang}`;
        }

        // Web Speech Recognition Integration
        let recognition = null;
        let isTranscribing = false;

        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRec();
            recognition.continuous = false;
            recognition.interimResults = false;

            // Map standard code to speech locale
            const localeMap = {
                'en': 'en-US',
                'fr': 'fr-FR',
                'ar': 'ar-SA',
                'sw': 'sw-KE',
                'ha': 'ha-NG',
                'yo': 'yo-NG',
                'ig': 'ig-NG'
            };
            recognition.lang = localeMap[currentLang] || 'en-US';

            recognition.onstart = () => {
                isTranscribing = true;
                document.getElementById('mic-icon').classList.add('text-brand-coral', 'animate-pulse');
                document.getElementById('chat-input').placeholder = "Listening... Speak now.";
            };

            recognition.onend = () => {
                isTranscribing = false;
                document.getElementById('mic-icon').classList.remove('text-brand-coral', 'animate-pulse');
                document.getElementById('chat-input').placeholder = "<?php echo $t['placeholder']; ?>";
            };

            recognition.onresult = (event) => {
                const text = event.results[0][0].transcript;
                document.getElementById('chat-input').value = text;
            };

            recognition.onerror = () => {
                isTranscribing = false;
                document.getElementById('mic-icon').classList.remove('text-brand-coral', 'animate-pulse');
                document.getElementById('chat-input').placeholder = "<?php echo $t['placeholder']; ?>";
            };
        }

        function toggleSpeech() {
            if (!recognition) {
                alert("Voice transcription is not supported by your current browser. Try Chrome or Safari.");
                return;
            }
            if (isTranscribing) {
                recognition.stop();
            } else {
                recognition.start();
            }
        }

        // Send Message via AJAX
        function sendMessage(event) {
            event.preventDefault();
            const inputField = document.getElementById('chat-input');
            const messageText = inputField.value.trim();
            if (!messageText) return;

            // Clear input
            inputField.value = '';

            // Render user bubble immediately
            const container = document.getElementById('messages-container');
            const userHtml = `
                <div class="flex items-start gap-3 max-w-[85%] ml-auto justify-end fade-in">
                    <div class="rounded-2xl rounded-tr-none bg-brand-sage text-white px-4 py-3 text-sm leading-relaxed shadow-sm">
                        ${escapeHtml(messageText)}
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', userHtml);
            container.scrollTop = container.scrollHeight;

            // Show typing indicator
            const typing = document.getElementById('typing-indicator');
            typing.classList.remove('hidden');

            // Send POST request
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', messageText);
            formData.append('language', currentLang);

            fetch('ai_companion.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                typing.classList.add('hidden');
                
                if (data.status === 'success') {
                    // Render AI bubble
                    const aiHtml = `
                        <div class="flex items-start gap-3 max-w-[85%] fade-in">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-brand-sageLight text-brand-sage text-xs">
                                🤖
                            </div>
                            <div class="rounded-2xl rounded-tl-none bg-brand-bg px-4 py-3 text-sm text-brand-slate leading-relaxed shadow-sm">
                                ${escapeHtml(data.ai_message)}
                            </div>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforeend', aiHtml);
                    container.scrollTop = container.scrollHeight;

                    // If crisis flagged
                    if (data.is_crisis) {
                        openEmergencyModal();
                    }
                } else {
                    console.error("AI Error:", data.error);
                }
            })
            .catch(err => {
                typing.classList.add('hidden');
                console.error("Connection Error:", err);
            });
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Box Breathing State Machine
        let breathingInterval = null;
        let breathingStateIndex = 0;
        let breathingTimerCount = 4;
        const breathingStates = [
            { label: 'Breathe In', css: 'breathing-inhale', duration: 4 },
            { label: 'Hold (Full)', css: 'breathing-hold-inhale', duration: 4 },
            { label: 'Breathe Out', css: 'breathing-exhale', duration: 4 },
            { label: 'Hold (Empty)', css: 'breathing-hold-exhale', duration: 4 }
        ];

        function toggleBreathing() {
            const btn = document.getElementById('breathing-btn');
            const circle = document.getElementById('breathing-circle');
            const stateText = document.getElementById('breathing-state');
            const timerText = document.getElementById('breathing-timer');

            if (breathingInterval) {
                // Stop breathing cycle
                clearInterval(breathingInterval);
                breathingInterval = null;
                btn.textContent = "Start Box Breathing Cycle";
                btn.classList.remove('bg-brand-sage', 'text-white');
                circle.className = "h-16 w-16 rounded-full bg-brand-sage opacity-75 breathing-circle flex items-center justify-center text-white text-xs font-bold font-outfit shadow-soft";
                circle.style.transform = '';
                circle.textContent = '';
                stateText.textContent = "Idle";
                timerText.textContent = "Click Start to Breathe";
            } else {
                // Start breathing cycle
                btn.textContent = "Stop Box Breathing";
                btn.classList.add('bg-brand-sage', 'text-white');
                breathingStateIndex = 0;
                breathingTimerCount = breathingStates[breathingStateIndex].duration;
                
                runBreathingStep();
                
                breathingInterval = setInterval(() => {
                    breathingTimerCount--;
                    if (breathingTimerCount <= 0) {
                        breathingStateIndex = (breathingStateIndex + 1) % 4;
                        breathingTimerCount = breathingStates[breathingStateIndex].duration;
                        runBreathingStep();
                    } else {
                        circle.textContent = breathingTimerCount + 's';
                        timerText.textContent = `${breathingStates[breathingStateIndex].label} (${breathingTimerCount}s remaining)`;
                    }
                }, 1000);
            }
        }

        function runBreathingStep() {
            const circle = document.getElementById('breathing-circle');
            const stateText = document.getElementById('breathing-state');
            const timerText = document.getElementById('breathing-timer');
            const currentStep = breathingStates[breathingStateIndex];

            // Reset transitions classes
            circle.className = "h-16 w-16 rounded-full opacity-75 breathing-circle flex items-center justify-center text-white text-xs font-bold font-outfit shadow-soft " + currentStep.css;
            circle.textContent = breathingTimerCount + 's';
            stateText.textContent = currentStep.label;
            timerText.textContent = `${currentStep.label} (${breathingTimerCount}s remaining)`;
        }

        // CBT Cognitive Reframer Logic
        const cognitiveTemplates = {
            'catastrophizing': [
                "While it feels like everything is going wrong, this is a heavy moment, not a lifetime sentence. I can focus on one small task right now rather than predicting the future.",
                "Expecting the worst is my anxiety talking. In reality, I have handled difficult moments before, and I will handle this step-by-step as well."
            ],
            'all-or-nothing': [
                "Making a mistake doesn't mean I am a failure. I am doing my best in a complex role, and doing some things well is enough. Growth is not binary.",
                "There is a middle ground. Just because today was extremely stressful doesn't mean I am not making progress. I can be imperfect and still succeed."
            ],
            'mind-reading': [
                "I cannot read minds. People are often focused on their own lives. Even if they judge me, their opinions do not define my worth or my efforts.",
                "Assuming others are thinking negatively about me is an anxious projection. I will focus on my own values instead of guessing other thoughts."
            ],
            'emotional-reasoning': [
                "Just because I feel overwhelmed doesn't mean I am incompetent. Feelings are not absolute facts. I can feel stressed and still be doing a great job.",
                "Feeling bad is a reflection of my fatigue, not a reflection of my capability. My efforts are valuable regardless of my temporary emotional state."
            ],
            'personalization': [
                "I am responsible for my actions, but I am not in control of everything. External factors, medical conditions, and other behaviors are not my fault.",
                "It is unrealistic to blame myself for circumstances beyond my direct control. I will focus on what I can influence and release the rest."
            ]
        };

        function reframeThought(event) {
            event.preventDefault();
            const thoughtText = document.getElementById('cbt-thought').value.trim();
            const distortionType = document.getElementById('cbt-distortion').value;
            const resultBox = document.getElementById('cbt-result-box');
            const reframedText = document.getElementById('cbt-reframed-text');

            if (!thoughtText || !distortionType) {
                alert("Please write your thought and select a distortion type first.");
                return;
            }

            const templates = cognitiveTemplates[distortionType] || cognitiveTemplates['catastrophizing'];
            const randomReframe = templates[Math.floor(Math.random() * templates.length)];

            reframedText.textContent = randomReframe;
            resultBox.classList.remove('hidden');
            resultBox.classList.add('fade-in');
        }

        // Gratitude journaling prompts generator
        const promptsDict = <?php echo json_encode($gratitude_prompts); ?>;

        function generateGratitudePrompt() {
            const list = promptsDict[currentLang] || promptsDict['en'];
            const randomPrompt = list[Math.floor(Math.random() * list.length)];
            document.getElementById('gratitude-text').textContent = randomPrompt;
        }

        // Emergency Modal Controls
        function openEmergencyModal() {
            const modal = document.getElementById('emergency-modal');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.querySelector('.relative').classList.remove('scale-95');
            modal.querySelector('.relative').classList.add('scale-100');
        }

        function closeEmergencyModal() {
            const modal = document.getElementById('emergency-modal');
            modal.classList.add('opacity-0', 'pointer-events-none');
            modal.querySelector('.relative').classList.add('scale-95');
            modal.querySelector('.relative').classList.remove('scale-100');
        }
    </script>
</body>
</html>

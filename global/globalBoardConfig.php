<?php
require __DIR__.DIRECTORY_SEPARATOR.'globalconfig.php';

// Default image directory name - this should be a relative path to your boards' directory
$config['IMG_DIR'] = 'src/';
// Thumb dir, same as IMG_DIR
$config['THUMB_DIR'] = 'src/';

// The amount of attachments a user can post at once per post
$config['ATTACHMENT_UPLOAD_LIMIT'] = 1;

// whether to redirect to the reply by default
$config['ALWAYS_NOKO'] = false;

// Show sage checkbox in post form
$config['USE_SAGE_CHECKBOX'] = true;
// Show noko checkbox in post form
$config['USE_NOKO_CHECKBOX'] = true;
// Show dump checkbox in post form
$config['USE_DUMP_CHECKBOX'] = true;

// Require new threads to have a file attached
$config['THREAD_ATTACHMENT_REQUIRED'] = true;

// Render a thread pager at the top of the thread, useful for large thread page sizes
$config['TOP_THREAD_PAGER'] = false;

// Replies shown (not including OP) per thread page
$config['REPLIES_PER_PAGE'] = 200;

$config['PROXYHEADERlist'] = array(
	'HTTP_CLIENT_IP',
	'HTTP_X_REAL_IP',
	'HTTP_X_FORWARDED_FOR',
	'HTTP_X_FORWARDED',
	'HTTP_X_CLUSTER_CLIENT_IP',
	'HTTP_FORWARDED_FOR',
	'HTTP_FORWARDED');
$config['FORTUNES'] = array( // Used for fortune function, selected at random.
	'Your true waifu will reveal herself',
	'Only time will tell',
	'Dark times are to come',
	'Your harem is only just begining',
	'You have cancer',
	'You have aids',
	'Tomo will strangle you in your sleep',
	'You just lost the game',
	'We don\'t know what happens next',
	'mah pen0z is bigger than uurz',
	'LOLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOL',
	'Bad luck',
	'Average luck',
	'Good luck',
	'Godly luck',
	'Very bad luck',
	'ｷﾀ━━━━━━(ﾟ∀ﾟ)━━━━━━ !!!!',
	'（　´_ゝ`）ﾌｰﾝ'
);

// Allowed filetypes and mimetypes
// The key is the extention and the value is the associated mime-type
$config['ALLOW_UPLOAD_EXT'] = [
	'gif'  => 'image/gif',
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'png'  => 'image/png',
	'bmp'  => 'image/bmp',
	'swf'  => 'application/x-shockwave-flash',
	'webm' => 'video/webm',
	'mp4'  => 'video/mp4',
	'mp3'  => 'audio/mpeg',
];


// Module List
// These are not all modules that come with kokonotsuba that you can enable, there are some unlisted ones too.
// See: https://github.com/Heyuri/kokonotsuba/wiki/All-modules
$config['ModuleList'] = array(
	/* modes */
	'catalog' => true,
	'search' => true,
	'threadList' => true,
	/* admin */
	'rebuild' => true,
	'adminDel' => true,
	'adminBan' => true,
	'fileBan' => true,
	'globalMessage' => true,
	'blotter' => true,
	'janitor' => true,
	'moveThread' => true,
	'rawHtml' => true,
	'deletedPosts' => true,
	'cssHax' => true,
	'notes' => true,
	'edit' => true,
	'perceptualBan' => true,
	/* thread modes */
	'autoSage' => true,
	'lockThread' => true,
	'sticky' => true,
	/* posting */
	'antiSpam' => true,
	'csrfPrevent' => true,
	'bbCode' => true,
	'emoji' => true,
	'wordFilter' => true,
	'countryFlags' => false,
	'antiFlood' => true,
	'fieldTraps' => true,
	'readOnly' => false,
	'viewPosts' => true,
	'displayId' => true,
	'dice' => true,
	'tripcode' => true,
	'displayIp' => true,
	'animatedGif' => true,
	'tegaki' => true,
	'quickReply' => true,
	'spoiler' => true,
	/* misc */
	'soudane' => true,
	'postApi' => true,
	'privateMessage' => true,
	'fullBanner' => true,
	'imageMeta' => true,
	'onlineCounter' => true,
	'ads' => true,
	'banner' => true,
	'addInfo' => true,
	'imageServer' => true,
	'filter' => true,
	'indexCommentTruncator' => true,
	'emotes' => true,
	'nameRandomizer' => false,
	'youtubeEmbed' => true
);

/* Module-specific options */
//mod_anigif
$config['ModuleSettings']['MAX_SIZE_FOR_ANIMATED_GIF'] = 2000; // Max file size for animated gifs (in kilobytes)

//mod_imagemeta
$config['ModuleSettings']['EXIF_DATA_VIEWER'] = false;
$config['ModuleSettings']['IMG_OPS'] = true; //imgops reverse image searcher portal
$config['ModuleSettings']['IQDB'] = false; //iqdb reverse image search protal
$config['ModuleSettings']['SWFCHAN'] = true; //swfchan archive

//mod_countryflags
$config['ModuleSettings']['FLAG_MODE'] = 1; // For the country flags module: 1 = hide flags on posts with "flag" in email field, 2 = show flags on posts with "flag" in email field

//mod_admindel
$config['ModuleSettings']['JANIMUTE_LENGTH'] = 20; // Janitor mute duration (in minutes)
$config['ModuleSettings']['JANIMUTE_REASON'] = 'You have been muted temporarily!'; // Janitor mute reason

//mod_antiflood
$config['ModuleSettings']['RENZOKU3'] = 30; // How many seconds between new threads?
$config['ModuleSettings']['SAME_COMMENT_TIME_WINDOW'] = 10; // How many seconds between posts that can have the same comment?
$config['ModuleSettings']['ALLOWED_COMMENT_REPETITIONS'] = 5; // How many times can the same comment be posted within the SAME_COMMENT_TIME_WINDOW before it starts deleting previous posts with that comment?

//mod_showip
$config['ModuleSettings']['IPTOGGLE'] = 1; // 1 to have OPs toggle IP display, 2 enables for all posts

//mod_blotter
$config['ModuleSettings']['BLOTTER_PREVIEW_AMOUNT'] = 5; //Number of previewed blotter entries on the index and thread view

//mod_pm
$config['ModuleSettings']['PM_DIR'] = __DIR__.DIRECTORY_SEPARATOR;
$config['ModuleSettings']['APPEND_TRIP_PM_BUTTON_TO_POST'] = false;

//fullBanner
$config['ModuleSettings']['SHOW_TOP_AD'] = true; // Whether to show the top full banner ad
$config['ModuleSettings']['SHOW_BOTTOM_AD'] = true; // Whether to show the bottom full banner ad
$config['ModuleSettings']['FULLBANNER_SUBMISSION_COOLDOWN'] = 300; // Seconds between banner submissions per IP
$config['ModuleSettings']['FULLBANNER_REQUIRED_WIDTH'] = 468; // Required banner image width in pixels
$config['ModuleSettings']['FULLBANNER_REQUIRED_HEIGHT'] = 60; // Required banner image height in pixels
$config['ModuleSettings']['FULLBANNER_MAX_FILE_SIZE'] = 204800; // Maximum banner file size in bytes (200KB)

//mod_wf
$config['ModuleSettings']['FILTERS'] = array( 
	'/\b(rabi-en-rose|rabi~en~rose)\b/i' => '<span class="rabienrose">Rabi~en~Rose</span>',
	'/\b(newfag)\b/i' => 'n00b like me',
	'/\b(newfags)\b/i' => 'n00bs like me',
	'/\b(heyuri★cgi)\b/i' => '<a href="https://wiki.heyuri.net/index.php?title=Heyuri%E2%98%85CGI">Heyuri★CGI</a>',
	'/\b(heyuri cgi)\b/i' => '<a href="https://wiki.heyuri.net/index.php?title=Heyuri%E2%98%85CGI">Heyuri★CGI</a>',
	'/\b(chat@heyuri)\b/i' => '<a href="https://cgi.heyuri.net/chat/">Chat@Heyuri</a>',
	'/\b(polls@heyuri)\b/i' => '<a href="https://cgi.heyuri.net/vote2/">Polls@Heyuri</a>',
	'/\b(dating@heyuri)\b/i' => '<a href="https://cgi.heyuri.net/dating/">Dating@Heyuri</a>',
	'/\b(uploader@heyuri)\b/i' => '<a href="https://up.heyuri.net/">Uploader@Heyuri</a>',
	'/@party 2/i' => '<a href="https://cgi.heyuri.net/party2/">@Party II</a>',
	'/@party ii/i' => '<a href="https://cgi.heyuri.net/party2/">@Party II</a>',
	'/\b(ayashii world)\b/i' => '<a href="https://wiki.heyuri.net/index.php?title=Ayashii_World">Ayashii World</a>',
	'/\b(partybus)\b/i' => '<span class="partybus"><span class="partybusColor1">p</span><span class="partybusColor2">a</span><span class="partybusColor3">r</span><span class="partybusColor4">t</span><span class="partybusColor5">y</span><span class="partybusColor6">b</span><span class="partybusColor7">u</span><span class="partybusColor8">s</span></span>',
	'/\b(boku)\b/i' => '<span class="boku" title="AGE OF DESU IS OVAR, WE BOKU NOW"><span class="bokuGreen">B</span><span class="bokuRed">O</span><span class="bokuGreen">K</span><span class="bokuRed">U</span></span>',
);

//mod_threadlist
$config['ModuleSettings']['THREADLIST_NUMBER'] = 50; // Number of lists displayed on one page
$config['ModuleSettings']['FORCE_SUBJECT'] = true; // Whether to force a new string to have a title
$config['ModuleSettings']['SHOW_IN_MAIN'] = true; // Whether to display on the main page
$config['ModuleSettings']['THREADLIST_NUMBER_IN_MAIN'] = 40; // Display the number of lists on the main page
$config['ModuleSettings']['SHOW_FORM'] = false; // Whether to display the delete form
$config['ModuleSettings']['HIGHLIGHT_COUNT'] = 15; // The number of popular responses, the number of responses exceeding this value will turn red (0 means not used)

//mod_onlinecounter
$config['ModuleSettings']['USER_COUNT_DAT_FILE'] = 'users.dat'; //Name of the file generated by mod_onlinecounter to keep track of how many IPs are viewing the page. Stored in board-storages for that board
$config['ModuleSettings']['USER_COUNT_TIMEOUT'] = 10; //Timeout for counting the amount of users. Counts in minutes

//mod_banner
$config['ModuleSettings']['BANNER_PATH'] = $config['STATIC_PATH'].'image/default/'; // Set this to the directory of your banner images

//mod_ads
$config['ModuleSettings']['ADS_STICKY_ROTATE_SECONDS'] = 45;
$config['ModuleSettings']['ADS_INLINE_EVERY_N_THREADS'] = 4; // Insert inline ad after every N threads. Requires at least N threads on the page.
$config['ModuleSettings']['ADS_INLINE_COUNT'] = 2; // Number of ads to show side-by-side in each inline row (1–5).
$config['ModuleSettings']['ADS_POST_AD_EVERY_N_POSTS'] = 15; // Insert a post-style ad after every N reply posts within a thread.

// Slot dimensions are fixed in layout, but containers still cap at max-width: 100%.
$config['ModuleSettings']['ADS_SLOT_DIMENSIONS'] = [
	'top' => ['width' => 728, 'height' => 90],
	'mobile' => ['width' => 300, 'height' => 250],
	'above' => ['width' => 728, 'height' => 150],
	'below' => ['width' => 728, 'height' => 150],
	'inline' => ['width' => 728, 'height' => 150],
	'post_ad' => ['width' => 300, 'height' => 250],
];

//mod_addinfo
$config['ModuleSettings']['ADD_INFO'] = array(
	'Read the <a href="//example.net/rules.html">rules</a> before you post.',
	'Read <a href="//example.net/faq.html">our FAQ</a> for any questions.',
	'Modify this by editing $config[\'ModuleSettings\'][\'ADD_INFO\'] in globalconfig.php',
);

//mod_globalmsg
$config['ModuleSettings']['GLOBAL_TXT'] = __DIR__.'/globalmsg.txt';

//mod_adminban
$config['DEFAULT_BAN_MESSAGE'] = '<p class="warning">(USER WAS BANNED FOR THIS POST) <img class="banIcon icon" alt="banhammer" src="'.$config['STATIC_URL'].'image'.DIRECTORY_SEPARATOR.'hammer.gif"></p>';

//mod_soudane
$config['ModuleSettings']['ENABLE_YEAH'] = true;
$config['ModuleSettings']['ENABLE_NOPE'] = false;
$config['ModuleSettings']['ENABLE_SCORE'] = false;
$config['ModuleSettings']['SHOW_SCORE_ONLY'] = false;

//mod_search
$config['ModuleSettings']['SEARCH_POSTS_PER_PAGE'] = 50;
$config['ModuleSettings']['SEARCH_TEMPLATE'] = 'kokoimg';
$config['ModuleSettings']['DISPLAY_THREADED_FORMAT'] = false;

//mod_readonly
$config['ModuleSettings']['ALLOW_REPLY'] = false; // allow replies to threads but disallow creating threads when board is read-only
$config['ModuleSettings']['MINIMUM_ROLE'] = Kokonotsuba\userRole::LEV_MODERATOR;

// posterID
$config['ModuleSettings']['DISP_ID'] = false; // if posterID is enabled: false = OPs can open by putting displayid to mail, true = IDs always ON

// deletedPosts
$config['ModuleSettings']['DELETED_POSTS_TEMPLATE'] = 'kokoimg';
$config['ModuleSettings']['PRUNE_TIME'] = 336;

// dice
$config['ModuleSettings']['DICE_AMOUNT_LIMIT'] = 30;
$config['ModuleSettings']['DICE_FACE_LIMIT'] = 9999;
$config['ModuleSettings']['EMAIL_DICE_ROLL'] = false;
$config['ModuleSettings']['COMMENT_DICE_ROLL'] = true;

/* bbCode */
$config['ModuleSettings']['supportBold'] = true; // [b]...[/b] into <b>...</b>
$config['ModuleSettings']['supportSpoiler'] = true; // [spoiler]...[/spoiler] into <span class="spoiler">...</span>
$config['ModuleSettings']['supportStrikeThrough'] = true; // [s]...[/s] into <s>...</s>
$config['ModuleSettings']['supportHeading'] = true; // [h]...[/h] into <span class="heading">...</span>
$config['ModuleSettings']['supportCode'] = true; // [code]...[/code] into <pre class="code">...</pre>
$config['ModuleSettings']['supportCodeBlocks'] = true; // complete code-block system ([code], [code=lang], syntax highlighting, extraction)
$config['ModuleSettings']['supportItalic'] = true; // [i]...[/i] into <i>...</i>
$config['ModuleSettings']['supportUnderline'] = true; // [u]...[/u] into <u>...</u>
$config['ModuleSettings']['supportParagraph'] = true; // [p]...[/p] into <p>...</p>
$config['ModuleSettings']['supportSw'] = true; // [sw]...[/sw] into <pre class="sw">...</pre> (Strange World-style AA)
$config['ModuleSettings']['supportColor'] = true; // [color=X]...[/color] into <span style="color:X;">...</span>
$config['ModuleSettings']['supportFontSize'] = true; // [sN]...[/sN] into <span class="fontSizeN">...</span>
$config['ModuleSettings']['supportPre'] = true; // [pre]...[/pre] into <pre>...</pre>
$config['ModuleSettings']['supportQuote'] = true; // [quote]...[/quote] into <blockquote>...</blockquote>
$config['ModuleSettings']['supportRuby'] = true; // ruby-related BBCodes into <ruby> / <rt> / <rp> tags
$config['ModuleSettings']['supportURL'] = false; // URL BBCodes via callback into <a href="http(s)/ftp://...">...</a>
$config['ModuleSettings']['supportEmail'] = false; // email BBCodes via callback into <a href="mailto:...">...</a>
$config['ModuleSettings']['supportImg'] = false; // [img]...[/img] into <img src="...">
$config['ModuleSettings']['supportScroll'] = true; // [scroll] conversion
$config['ModuleSettings']['supportKao'] = true; // [kao]...[/kao] into <span class="ascii">...</span>

$config['ModuleSettings']['NAME_RANDOMIZER_NAMES'] = [
	'Shota Liberation Army',
	'Nyuu',
	'Nyo',
	'Ootani-san',
	'Onsokumaru',
	'Shinobu',
	'Sasuke',
	'Madarame',
	'oono',
	'Saki',
	'Saya',
	'Rabi-en-rose',
	'Momoha',
	'Hitomi Tanaka',
	'Gokiburu',
	'Porkchop',
	'1931, the king of /b/',
	'Six figures',
	'Tululoo',
	'Puchippy',
	'Alice',
	'Roach',
	'Meta44',
	'Spemwaxx',
	'Iruyeh-tan',
	'Lolicious',
	'Chiyo Penguin',
	'The Balancer',
	'Fortune God',
	'Vomit Snake',
	'|ω・`)じー',
	'Richard Lewis',
	'Spam-kun',
	'Wormhole-kun',
	'x7m0d',
	'Fall in love with ME',
	'Imouto-san',
	'Umaru',
	'Onii-chan',
	'Kimura-sensei',
	'Osaka',
	'Sakaki-san',
	'Mint',
	'Milfeulle',
	'Forte',
	'Banira',
	'Matsuri',
	'Miu',
	'Nobue-onee-chan',
	'Sasazuka',
	'Ana',
	'ZA PEIPA',
	'MISTER GENTLEMEN',
	'Miss Deepu',
	'UUUUUUU~',
	'Jun',
	'Suiseiseki',
	'Souseiseki',
	'Kirakishou',
	'Suigintou',
	'Nori (talking boob)',
	'Rei',
	'Ayanami',
	'Asuka',
	'Gendo',
	'Fuyutsuki',
	'Ikari Yui',
	'Adam',
	'Lilith',
	'Puchiko',
	'Dejiko',
	'Hikaru Usada',
	'Manager-san',
	'Gema',
	'Abarenbou',
	'Remilia Scarlet',
	'Patchouli',
	'Marisa',
	'Reimu',
	'Tenshi',
	'Aya',
	'Chris Handson',
	'Hanekawa',
	'Mararagi',
	'Biscuit-tan',
	'Habanero-tan',
	'hiroyuki',
	'Grimm',
];

// Emotes that will show up if the emote module is enabled
// it works when the user puts the name of the emote between :: in their post comment
// e.g :nigra: gets turned into '<img src="nigra.gif">'
// NOTE: Emotes *must* be in `static/image/emotes/`
$config['ModuleSettings']['EMOTES'] = [
	'angry'		=>'emo-yotsuba-angry.gif',
	'astonish'	=>'emo-yotsuba-astonish.gif',
	'biggrin'	=>'emo-yotsuba-biggrin.gif',
	'closed-eyes'=>'emo-yotsuba-closed-eyes.gif',
	'closed-eyes2'=>'emo-yotsuba-closed-eyes2.gif',
	'cool'		=> 'emo-yotsuba-cool.gif',
	'cry'		=> 'emo-yotsuba-cry.gif',
	'dark'		=>'emo-yotsuba-dark.gif',
	'dizzy'		=>'emo-yotsuba-dizzy.gif',
	'drool'		=>'emo-yotsuba-drool.gif',
	'glare'		=>'emo-yotsuba-glare.gif',
	'glare1'	=>'emo-yotsuba-glare-01.gif',
	'glare2'	=>'emo-yotsuba-glare-02.gif',
	'happy'		=>'emo-yotsuba-happy.gif',
	'huh'		=>'emo-yotsuba-huh.gif',
	'nosebleed'	=>'emo-yotsuba-nosebleed.gif',
	'nyaoo'		=>'emo-yotsuba-nyaoo.gif',
	'nyaoo2'	=>'emo-yotsuba-nyaoo2.gif',
	'nyaoo-closedeyes'	=>'emo-yotsuba-nyaoo-closedeyes.gif',
	'ph34r'		=>'emo-yotsuba-ph34r.gif',
	'ninja'		=>'emo-yotsuba-ph34r.gif',
	'rolleyes'	=>'emo-yotsuba-rolleyes.gif',
	'rollseyes'	=>'emo-yotsuba-rolleyes.gif',
	'sad'		=>'emo-yotsuba-sad.gif',
	'smile'		=>'emo-yotsuba-smile.gif',
	'sweat'		=>'emo-yotsuba-sweat.gif',
	'sweat2'	=>'emo-yotsuba-sweat2.gif',
	'sweat3'	=>'emo-yotsuba-sweat3.gif',
	'tongue'	=>'emo-yotsuba-tongue.gif',
	'unsure'	=>'emo-yotsuba-unsure.gif',
	'wink'		=>'emo-yotsuba-wink.gif',
	'x3'		=>'emo-yotsuba-x3.gif',
	'xd'		=>'emo-yotsuba-xd.gif',
	'xp'		=>'emo-yotsuba-xp.gif',
	'love'		=>'emo-yotsuba-heart.gif',
	'blush'		=>'emo-yotsuba-blush3.gif',
	'mask'		=>'emo-yotsuba-mask.gif',
	'emo'		=>'emo.gif',
	'lolico'	=>'emo-yotsuba-lolico.gif',
	'kuz'		=>'emo-yotsuba-tomo.gif',
	'dance'		=>'heyuri-dance.gif',
	'dance2'	=>'heyuri-dance-pantsu.gif',
	'nigra'		=>'nigra.gif',
	'sage'		=>'sage.gif',
	'longcat'	=>'longcat.gif',
	'tacgnol'	=>'tacgnol.gif',
	'mona2'		=>'mona2.gif',
	'nida'		=>'nida.gif',
	'iyahoo'	=>'iyahoo.gif',
	'banana'	=>'banana.gif',
	'onigiri'	=>'onigiri.gif',
	'shii'		=>'anime_shii01.gif',
	'saitama'	=>'anime_saitama05.gif',
	'foruda'	=>'foruda.gif',
	'nagato'	=>'nagato.gif',
	'kuma6'		=>'kuma6.gif',
	'waha'		=>'waha.gif',
	'hokke'		=>'hokke.gif',
	'af2'		=>'af2.gif',
	'pata'		=>'u_pata.gif',
	'depression'=>'u_sasu.gif',
	'saitama2'	=>'anime_saitama06.gif',
	'monapc'	=>'anime_miruna_pc.gif',
	'purin'		=>'purin.gif',
	'ranta'		=>'anime_imanouchi04.gif',
];

// Kaomoji (Shift-JIS faces) shown in the post form
// 'display text' => 'value inserted into comment'
$config['ModuleSettings']['KAOMOJI'] = [
	'ヽ(´ー｀)ノ' => '[kao]ヽ(´ー｀)ノ[/kao]',
	'(;´Д`)' => '[kao](;´Д`)[/kao]',
	'ヽ(´∇`)ノ' => '[kao]ヽ(´∇`)ノ[/kao]',
	'(´人｀)' => '[kao](´人｀)[/kao]',
	'(＾Д^)' => '[kao](＾Д^)[/kao]',
	'(´ー`)' => '[kao](´ー`)[/kao]',
	'（ ´,_ゝ`）' => '[kao]（ ´,_ゝ`）[/kao]',
	'(´～`)' => '[kao](´～`)[/kao]',
	'(;ﾟДﾟ)' => '[kao](;ﾟДﾟ)[/kao]',
	'(;ﾟ∀ﾟ)' => '[kao](;ﾟ∀ﾟ)[/kao]',
	'┐(ﾟ～ﾟ)┌' => '[kao]┐(ﾟ～ﾟ)┌[/kao]',
	'ヽ(`Д´)ノ' => '[kao]ヽ(`Д´)ノ[/kao]',
	'( ´ω`)' => '[kao]( ´ω`)[/kao]',
	'(ﾟー｀)' => '[kao](ﾟー｀)[/kao]',
	'(・∀・)' => '[kao](・∀・)[/kao]',
	'（⌒∇⌒ゞ）' => '[kao]（⌒∇⌒ゞ）[/kao]',
	'(ﾟ血ﾟ#)' => '[kao](ﾟ血ﾟ#)[/kao]',
	'(ﾟｰﾟ)' => '[kao](ﾟｰﾟ)[/kao]',
	'(´￢`)' => '[kao](´￢`)[/kao]',
	'(´π｀)' => '[kao](´π｀)[/kao]',
	'ヽ(ﾟρﾟ)ノ' => '[kao]ヽ(ﾟρﾟ)ノ[/kao]',
	'Σ(;ﾟДﾟ)' => '[kao]Σ(;ﾟДﾟ)[/kao]',
	'Σ(ﾟдﾟ|||)' => '[kao]Σ(ﾟдﾟ|||)[/kao]',
	'ｷﾀ━━━(・∀・)━━━!!' => '[kao]ｷﾀ━━━(・∀・)━━━!![/kao]',
];

/* indexCommentTruncator */
$config['ModuleSettings']['CHARACTER_PREVIEW_LIMIT'] = 2500;
$config['ModuleSettings']['LINE_PREVIEW_LIMIT'] = 10;

$config['ModuleSettings']['NAME_RANDOMIZER_TTL'] = 43200; 

$config['SWF_THUMB'] = $config['STATIC_URL']."image/swf_thumb.png";
$config['AUDIO_THUMB'] = $config['STATIC_URL']."image/audio.png";
$config['ARCHIVE_THUMB'] = $config['STATIC_URL']."image/archive.png";

/*---- Part 2：Board Functions ----*/
$config['HOME'] = 'index.html'; // What the [Home] button links to
$config['TOP_LINKS'] = @file_get_contents(__DIR__.'/toplinks.txt'); // Navigation links at top left

$config['COMM_MAX'] = 5000; // How many characters in comment
$config['INPUT_MAX'] = 100; // Maximum non-message characters
$config['BR_CHECK'] = 0; // How many lines to show
$config['STATIC_HTML_UNTIL'] = 10; // Static web pages automatically generated when a new article to the first few pages (all generated:-1 only portal pages: 0)
$config['GZIP_COMPRESS_LEVEL'] = 0; // Compression level with gzip 1 - 9

$config['DEFAULT_NOTITLE'] = ''; // Default title if none is inputted
$config['DEFAULT_NONAME'] = 'Anonymous'; // Default Name
$config['DEFAULT_NOCOMMENT'] = 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!'; // Default comment
$config['MINIFY_HTML'] = 0; //Removes unnecessary whitespace in HTML file

// Image Restrictions
$config['MAX_KB'] = 9000; // Image Upload Capacity in KB. Note: To set it higher. you will have to mess with php.ini due to the maximum being 2mb.
$config['STORAGE_LIMIT'] = 0; // Storage limit
$config['STORAGE_MAX'] = 300000; // Total storage limit

$config['AUTO_LINK'] = 1; // Create urls (autolink)

// Footer at the bottom of the page
$config['FOOTTEXT'] = '';

$config['REF_URL'] = ''; // URL prefix, eg: https://jump.heyuri.net

$config['VIDEO_EXT'] = 'WEBM|MP4'; // What filetypes will be loaded as a video

$config['ALLOW_NONAME'] = 1; // Allow posters to submit without names
$config['CLEAR_SAGE'] = 0; // Disable sage if true
$config['NOTICE_SAGE'] = 1; // Visible sage ("SAGE!")
$config['USE_QUOTESYSTEM'] = 1; // Enable >>1234
$config['SHOW_IMGWH'] = 1; // Display the original length and width dimension of the additional image file
$config['RENDER_REPLY_NUMBER'] = true; // Show the sequential reply number for each post within a thread (does not change if posts are deleted)

$config['PAGE_DEF'] = 15; // How many threads per page
$config['ADMIN_PAGE_DEF'] = 100; // How many replies per page on admin panel
$config['RE_DEF'] = 5; // Shown Replies on Index
$config['RE_PAGE_DEF'] = 1000; // Shown replies on the thread index
$config['MAX_RES'] = 1000; // How many replies before autosaged

$config['TEXTBOARD_ONLY'] = 0; // Completely disables all file features
$config['RESIMG'] = 1; // Allow files in replies

// Continuous Posting Time Limits
$config['RENZOKU'] = 0; // Post limit, intervals in seconds
$config['RENZOKU2'] = 0; // Post limit for images, intervals in seconds


$config['TRUST_HTTP_X_FORWARDED_FOR'] = 0; //Whether to use HTTP_X_FORWARDED_FOR to grab the real IP after the Proxy. Note that the file head may be forged, do not open if there is no special need.

// Appearance
$config['MAX_THREAD_AMOUNT'] = 150; // Auto deletes the last thread from a board that exceed this limit, defaults to 10 pages

// Appearance
$config['TEMPLATE_FILE'] = 'kokoimg'; // Template directory. Set this and the next to 'kokotxt' and 'kokotxtreply' respectively for textboard.
$config['REPLY_TEMPLATE_FILE'] = 'kokoimg'; // Reply page template directory
$config['MAX_AGE_TIME'] = 0; // How long will thread accept age replies? (hours)

$config['USE_CATEGORY'] = 0; // Enable Categories

$config['PREVENT_DUPLICATE_FILE_UPLOADS'] = false; // Disallow the same file was being posted twice 
$config['DUPLICATE_FILE_TIME'] = 7200; // The time a duplicate attachment cant be uploaded

// Ban Settings
$config['BAN_CHECK'] = 1; // Comprehensive ban check function
$config['GLOBAL_BANS'] = 'globalbans.log'; //global bans file name. The file is stored in `global/`

// Webhooks for post notifications
$config['DISCORD_WH'] = '';
$config['IRC_WH'] = '';


$config['TIME_ZONE'] = '0'; // Timezones, 0 is UTC. Example: '-4' for New York, or '9' for Japan
$config['HTTP_UPLOAD_DIFF'] = 50; 


// Overboard title and sub-title
$config['OVERBOARD_TITLE'] = "Overboard";
$config['OVERBOARD_SUBTITLE'] = "Posts from all koko boards";
// HTML that will appear above the filter box
$config['OVERBOARD_SUB_HEADER_HTML'] = '';
// How many threads per page on the overboard
$config['OVERBOARD_THREADS_PER_PAGE'] = 20;
// A link to the overboard on the admin bar (next to [Admin] on the top right). Displayed as [Overboard]
$config['ADMINBAR_OVERBOARD_BUTTON'] = true;

$config['ACTIONLOG_MAX_PER_PAGE'] = 50; // the amount of actionlog entries per page

// The duration (in seconds) of inactivity allowed before automatically logging out a staff user
// This value must not exceed the value of session.gc_maxlifetime in your php.ini
$config['STAFF_LOGIN_TIMEOUT'] = 86400;

// name of system role/user
$config['SYSTEMCHAN_NAME'] = "System-chan";

// Time limit for deleting posts in hours.
$config['POST_DELETION_TIME_LIMIT'] = 168;

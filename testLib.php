<?php
function getExtensionByMimeType($mimeType) {
    $mimeMap = [
        // Images
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/gif' => '.gif',
        'image/webp' => '.webp',
        'image/svg+xml' => '.svg',
        'image/tiff' => '.tiff',
        'image/bmp' => '.bmp',
        'image/vnd.microsoft.icon' => '.ico',
        'image/heic' => '.heic',
        
        // Video
        'video/mp4' => '.mp4',
        'video/x-msvideo' => '.avi',
        'video/x-ms-wmv' => '.wmv',
        'video/mpeg' => '.mpeg',
        'video/quicktime' => '.mov',
        'video/webm' => '.webm',
        'video/ogg' => '.ogv',
        'video/x-flv' => '.flv',
        
        // Audio
        'audio/mpeg' => '.mp3',
        'audio/ogg' => '.ogg',
        'audio/wav' => '.wav',
        'audio/x-aac' => '.aac',
        'audio/x-ms-wma' => '.wma',
        'audio/flac' => '.flac',
        'audio/x-midi' => '.midi',
        
        // Documents
        'application/pdf' => '.pdf',
        'application/msword' => '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'application/vnd.ms-excel' => '.xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
        'application/vnd.ms-powerpoint' => '.ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx',
        'text/plain' => '.txt',
        'text/csv' => '.csv',
        'text/html' => '.html',
        'application/rtf' => '.rtf',
        'application/xml' => '.xml',
        'application/json' => '.json',
        
        // Archives
        'application/zip' => '.zip',
        'application/x-rar-compressed' => '.rar',
        'application/x-7z-compressed' => '.7z',
        'application/x-tar' => '.tar',
        'application/x-bzip' => '.bz',
        'application/x-bzip2' => '.bz2',
        'application/x-gzip' => '.gz',
        
        // Fonts
        'font/otf' => '.otf',
        'font/ttf' => '.ttf',
        'font/woff' => '.woff',
        'font/woff2' => '.woff2',
        
        // Others
        'application/vnd.adobe.flash.movie' => '.swf',
        'application/x-shockwave-flash' => '.swf',
        'application/vnd.android.package-archive' => '.apk',
        'application/x-apple-diskimage' => '.dmg',

        // Images
        'image/x-canon-cr2' => '.cr2',
        'image/x-canon-crw' => '.crw',
        'image/x-epson-erf' => '.erf',
        'image/x-fuji-raf' => '.raf',
        'image/x-nikon-nef' => '.nef',
        'image/x-olympus-orf' => '.orf',
        'image/x-panasonic-raw' => '.raw',
        'image/x-sony-arw' => '.arw',
        
        // eBooks
        'application/epub+zip' => '.epub',
        'application/x-mobipocket-ebook' => '.mobi',
        'application/x-ms-reader' => '.lit',

        // 3D Models
        'model/stl' => '.stl',
        'model/obj' => '.obj',
        'model/gltf-binary' => '.glb',
        'model/gltf+json' => '.gltf',
        
        // Scripts
        'application/javascript' => '.js',
        'application/x-python-code' => '.py',
        'application/x-ruby' => '.rb',
        'text/x-c' => '.c',
        'text/x-csharp' => '.cs',
        'text/x-c++' => '.cpp',
        'text/x-java-source' => '.java',
        'text/x-php' => '.php',
        'application/x-perl' => '.pl',
        'application/x-shellscript' => '.sh',
        
        // Markup/Stylesheets
        'text/css' => '.css',
        'text/markdown' => '.md',
        'application/xhtml+xml' => '.xhtml',
        'text/xml' => '.xml',
                
        // Fonts
        'application/x-font-ttf' => '.ttf',
        'application/x-font-otf' => '.otf',
        'application/font-woff' => '.woff',
        'application/font-woff2' => '.woff2',
        
        // Executables
        'application/x-msdownload' => '.exe',
        'application/x-ms-installer' => '.msi',
        
        // Add more MIME types as needed
    ];

    return $mimeMap[$mimeType] ?? false; // Return false if MIME type is not found
}

function isIPBanned($ip){
    return false;
}
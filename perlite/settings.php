<?php

// --- Custom .env Loader for Hot Reloading ---
// Reads /var/www/perlite/.env directly so changes take effect on page refresh
$envFile = '/var/www/perlite/.env';
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $envVars[trim($name)] = trim($value);
        }
    }
}

// Helper function to get from loaded env or fallback
function getEnvVal($key, $default = null) {
    global $envVars;
    return isset($envVars[$key]) ? $envVars[$key] : (getenv($key) ?: $default);
}

// --- General Settings ---
$rootDir = getEnvVal('NOTES_PATH', 'Demo');
$index = getEnvVal('HOME_FILE', 'README');
$siteTitle = getEnvVal('SITE_TITLE', 'Perlite');


// --- Frontend Settings ---
$lineBreaks = filter_var(getEnvVal('LINE_BREAKS', true), FILTER_VALIDATE_BOOLEAN);
$disablePopHovers = filter_var(getEnvVal('DISABLE_POP_HOVER', false), FILTER_VALIDATE_BOOLEAN);
$showTOC = filter_var(getEnvVal('SHOW_TOC', true), FILTER_VALIDATE_BOOLEAN);
$showLocalGraph = filter_var(getEnvVal('SHOW_LOCAL_GRAPH', true), FILTER_VALIDATE_BOOLEAN);
$font_size = getEnvVal('FONT_SIZE', '15');
$hideFolders = getEnvVal('HIDE_FOLDERS', 'docs,trash');
$niceLinks = filter_var(getEnvVal('NICE_LINKS', true), FILTER_VALIDATE_BOOLEAN);


// --- Advanced Settings ---
$hiddenFileAccess = filter_var(getEnvVal('HIDDEN_FILE_ACCESS', false), FILTER_VALIDATE_BOOLEAN);
$absolutePath = filter_var(getEnvVal('ABSOLUTE_PATHS', false), FILTER_VALIDATE_BOOLEAN);
$uriPath = "/";
$htmlSafeMode = filter_var(getEnvVal('HTML_SAFE_MODE', true), FILTER_VALIDATE_BOOLEAN);
$useZettelkastenFilenames = filter_var(getEnvVal('ZETTELKASTEN_FILENAMES_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
$highlightJSLangs = explode(",", getEnvVal('HIGHLIGHTJS_LANGS', "powershell,x86asm"));
$allowedFileLinkTypes = explode(",", getEnvVal('ALLOWED_FILE_LINK_TYPES', "pdf,mp4"));
$tempPath = getEnvVal('TEMP_PATH', "");  


// --- Metadata Settings ---
$siteType = getEnvVal('SITE_TYPE', "article");
$siteImage = getEnvVal('SITE_IMAGE', "https://raw.githubusercontent.com/secure-77/Perlite/main/screenshots/screenshot.png");
$siteURL = getEnvVal('SITE_URL', "https://perlite.secure77.de");
$siteDescription = getEnvVal('SITE_DESC', "A web based markdown viewer optimized for Obsidian Notes");
$siteName = getEnvVal('SITE_NAME', "Perlite Demo");


// --- Profile Settings ---
$siteLogo = getEnvVal('SITE_LOGO', "perlite.svg");  
$siteHomepage = getEnvVal('SITE_HOMEPAGE', ""); 
$siteGithub = getEnvVal('SITE_GITHUB', "https://github.com/secure-77");  
$siteTwitter = getEnvVal('SITE_TWITTER', "@secure_sec77");

?>
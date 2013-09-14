// KrISS dwld: a simple and smart (or stupid) downloader bookmarklet
// Copyleft - Tontof - http://tontof.net
// use KrISS dwld at your own risk
(function(){
    var
    version = 1,
    src     = '',
    host    = window.location.hostname;
    
    switch (host){
    case 'www.youtube.com':
        src = '//tontof.net/kriss/dwld/youtube.php?url='+decodeURIComponent(ytplayer.config.assets.js)+'&';
        break;
    default:
        src = "//tontof.net/kriss/dwld/others.js?";
    }

    if (src !== '') {
        var z = document.createElement("script");
        z.src = src + 'version=' + version;
        document.body.appendChild(z);
    }
})()

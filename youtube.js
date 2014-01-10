/* jshint global yt:false */
(function(){
    'use strict';

    function Rk(a){a=a.split("");a=a.reverse();a=a.slice(1);a=Sk(a,1);a=a.reverse();a=Sk(a,43);a=a.reverse();a=a.slice(1);a=a.reverse();return a.join("")}
    function Sk(a,b){var c=a[0];a[0]=a[b%a.length];a[b]=c;return a};
    function ytSig(d){return Rk(d);}

    if (window.location.href.indexOf('/embed/') !== -1) {
        window.location.assign(window.location.href.replace('/embed/', '/watch?v='));
    } else {
        // http://en.wikipedia.org/wiki/YouTube#Quality_and_codecs
        // https://userscripts.org/scripts/review/25105
        var i = 0,
            FORMAT_LABEL={'5':'FLV 240p','18':'MP4 360p','22':'MP4 720p (HD)','34':'FLV 360p','35':'FLV 480p','37':'MP4 1080p (HD)','38':'MP4 4K (HD)','43':'WebM 360p','44':'WebM 480p','45':'WebM 720p (HD)','46':'WebM 1080p (HD)'},
            player = document.getElementById('movie_player')||document.getElementById('movie_player-flash'),
            tab = [],
            videoFormats,
            vars = {};

        if (player) {
            tab = player.getAttribute('flashvars').split('&');
            for (i = 0; i < tab.length; i++) {
                vars[tab[i].split('=')[0]] = tab[i].split('=')[1];
            }
            videoFormats = decodeURIComponent(vars.url_encoded_fmt_stream_map);
        } else {
            if (typeof(yt) != 'undefined' && yt.playerConfig) {
                videoFormats = yt.playerConfig.args.url_encoded_fmt_stream_map;
            } else {
                return;
            }
        }

        // parse the formats map
        var videoURL={};
        var videoFormatsGroup=videoFormats.split(',');
        for (i=0;i<videoFormatsGroup.length;i++) {
            var videoFormatsElem=videoFormatsGroup[i].split('&');
            var videoFormatsPair={};
            for (var j=0;j<videoFormatsElem.length;j++) {
                var pair=videoFormatsElem[j].split('=');
                if (pair.length===2) {
                    videoFormatsPair[pair[0]]=pair[1];
                }
            }
            var url=(videoFormatsPair.url)?videoFormatsPair.url:null;
            if (url===null){
              continue;
            }
            var itag=(videoFormatsPair.itag)?videoFormatsPair.itag:null;
            if (itag===null){
              continue;
            }
            var signature=(videoFormatsPair.sig)?videoFormatsPair.sig:null;
            if (signature==null) {
               signature=ytSig(videoFormatsPair.s);
            }
            if (signature!==null) {
                videoFormatsPair.url=decodeURIComponent(url)+'&signature='+signature;
            }
            videoFormatsPair.type = decodeURIComponent(videoFormatsPair.type);
            if (url.toLowerCase().indexOf('http')===0) { // validate URL
                videoURL[itag]=videoFormatsPair;
            }
        }

        var s = '';
        var list = [];
        for (var type in videoURL) {
            if (FORMAT_LABEL[type]) {
                list.push(type);
                s += list.length +' => '+FORMAT_LABEL[type]+'\n';
            }
        }
        var r = parseInt(window.prompt(s),10)-1;
        if (r < list.length) {
            window.location.assign(videoURL[list[r]].url);
        }
    }
})();

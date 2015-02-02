/* jshint global yt:false */
(function(){
    'use strict';

    var flashvars,
        subtitles,
        parseTree = function(element, value) {
            var result;
            if (typeof(element) == 'object' ) {
                for (var i in element) {
                    if (i == value) {
                        result = element[i];
                        break;
                    } else {
                        var tmp = parseTree(element[i], value);
                        if (typeof tmp != 'undefined') {
                            result = tmp;
                            break;
                        }
                    }
                }
            }
            return result;
        }
    
    $.each($('#container_player_main').find('param[name=flashvars]').val().split('&'),
           function(index, value){
               if (value.startsWith('sequence')){
                   value = unescape(value).substr(9);
                   flashvars=JSON.parse(value);
               }
           });

    subtitles = parseTree(flashvars, 'availableSubtitles');

    var s = '';
    var list = [];
    for (var type in subtitles) {
        list.push(subtitles[type]);
        s += list.length +' => '+type+'\n';
    }
    var r = parseInt(window.prompt(s),10)-1;
    if (r < list.length) {
        window.location.assign(list[r]);
    }
})();

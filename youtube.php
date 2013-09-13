<?php
// based on http://userscripts.org/scripts/review/25105 (MIT License)
//   
// Modified by Tontof for KrISS dwld
// http://tontof.net/kriss/dwld

header('Content-type: text/javascript; charset=UTF-8');
?>(function () {
  var FORMAT_LABEL={'5':'FLV 240p','18':'MP4 360p','22':'MP4 720p (HD)','34':'FLV 360p','35':'FLV 480p','37':'MP4 1080p (HD)','38':'MP4 4K (HD)','43':'WebM 360p','44':'WebM 480p','45':'WebM 720p (HD)','46':'WebM 1080p (HD)'};
  var FORMAT_TYPE={'5':'flv','18':'mp4','22':'mp4','34':'flv','35':'flv','37':'mp4','38':'mp4','43':'webm','44':'webm','45':'webm','46':'webm'};
  var FORMAT_ORDER=['5','18','34','43','35','44','22','45','37','46','38'];
  var FORMAT_RULE={'flv':'max','mp4':'all','webm':'none'};
  // all=display all versions, max=only highest quality version, none=no version  
  // the default settings show all MP4 videos, the highest quality FLV and no WebM
 var BUTTON_TEXT={'ar':'تنزيل','cs':'Stáhnout','de':'Herunterladen','en':'Download','es':'Descargar','fr':'Télécharger','hi':'डाउनलोड','id':'Unduh','it':'Scarica','ja':'ダウンロード','pl':'Pobierz','pt':'Baixar','ro':'Descărcați','ru':'Скачать','tr':'İndir','zh':'下载'};
  var BUTTON_TOOLTIP={'ar':'تنزيل هذا الفيديو','cs':'Stáhnout toto video','de':'Dieses Video herunterladen','en':'Download this video','es':'Descargar este vídeo','fr':'Télécharger cette vidéo','hi':'वीडियो डाउनलोड करें','id':'Unduh video ini','it':'Scarica questo video','ja':'このビデオをダウンロードする','pl':'Pobierz plik wideo','pt':'Baixar este vídeo','ro':'Descărcați acest videoclip','ru':'Скачать это видео','tr': 'Bu videoyu indir','zh':'下载此视频'};
  var DECODE_RULE={};
  var RANDOM=7489235179; // Math.floor(Math.random()*1234567890);
  var CONTAINER_ID='download-youtube-video'+RANDOM;
  var LISTITEM_ID='download-youtube-video-fmt'+RANDOM;
  var BUTTON_ID='download-youtube-video-button'+RANDOM;
  var DEBUG_ID='download-youtube-video-debug-info';
  var STORAGE_URL='download-youtube-script-url';
  var STORAGE_CODE='download-youtube-signature-code';
  var isDecodeRuleUpdated=false;  
    
  start();
      
function start() {  
  var pagecontainer=document.getElementById('page-container');
  if (!pagecontainer) return;
  if (/^https?:\/\/www\.youtube.com\/watch\?/.test(window.location.href)) run();     
  var isAjax=/class[\w\s"'-=]+spf\-link/.test(pagecontainer.innerHTML);
  var content=document.getElementById('content');
  if (isAjax && content) { // Ajax UI    
      var mo=window.MutationObserver||window.MozMutationObserver||window.WebKitMutationObserver;
      if(typeof mo!=='undefined') {
        var observer=new mo(function(mutations) {
          mutations.forEach(function(mutation) {
              if(mutation.addedNodes!==null) {
                for (var i=0; i<mutation.addedNodes.length; i++) {
                    if (mutation.addedNodes[i].id=='watch7-container') { // old value: movie_player
                      run(); 
                      break;
                    }
                }
              }
          });
        });
        observer.observe(content, {childList: true, subtree: true}); // old value: pagecontainer
      } else { // MutationObserver fallback for old browsers
        pagecontainer.addEventListener('DOMNodeInserted', onNodeInserted, false);
      }
  } 
}

function onNodeInserted(e) { 
    if (e && e.target && e.target.id=='watch7-container') { // old value: movie_player
      run();
  }
}

function run() {
  if (document.getElementById(CONTAINER_ID)){
    kriss_dwld();
    return; // check download container
  }
  if (document.getElementById('p') && document.getElementById('vo')) return; // Feather not supported

  var videoID, videoTicket, videoFormats, scriptURL=null;
  var isSignatureCodeUpdated=false;
  var language=findLanguage();
  var textDirection='left';
  if (document.body.getAttribute('dir')=='rtl') {
    textDirection='right';
  }
  fixTranslations(language, textDirection);
        
  // obtain video ID, temporary ticket, formats map   
  
  var args=null;
  var usw=getUnsafeWindow(); // Firefox, Opera<15
  if (usw.ytplayer && usw.ytplayer.config && usw.ytplayer.config.args) {
    args=usw.ytplayer.config.args;
  }
  if (args) {
    videoID=args['video_id'];
    videoTicket=args['t'];
    videoFormats=args['url_encoded_fmt_stream_map'];
    debug('DYVAM - Info: Standard mode. videoID '+(videoID?videoID:'none')+'; ');
  }
  if (usw.ytplayer && usw.ytplayer.config && usw.ytplayer.config.assets) {
    scriptURL=usw.ytplayer.config.assets.js;
  }  
  
  if (videoID==null || videoTicket==null) { // unsafeWindow workaround (Chrome, Opera 15+)
    if (document.getElementById(DEBUG_ID+'2')==null) {
      createHiddenElem('pre', DEBUG_ID+'2');
    }    
    injectScript ('document.getElementById("'+DEBUG_ID+'2").appendChild(document.createTextNode(\'"video_id":"\'+ytplayer.config.args.video_id+\'", "t":"\'+ytplayer.config.args.t+\'", "js":"\'+ytplayer.config.assets.js+\'", "url_encoded_fmt_stream_map":"\'+ytplayer.config.args.url_encoded_fmt_stream_map+\'"\'));');            
    var code=document.getElementById(DEBUG_ID+'2').innerHTML;
    if (code) {
      videoID=findMatch(code, /\"video_id\":\s*\"([^\"]+)\"/);
      videoTicket=findMatch(code, /\"t\":\s*\"([^\"]+)\"/);
      videoFormats=findMatch(code, /\"url_encoded_fmt_stream_map\":\s*\"([^\"]+)\"/);
      videoFormats=videoFormats.replace(/&amp;/g,'\\u0026');
      scriptURL=findMatch(code, /\"js\":\s*\"([^\"]+)\"/);
    }
    debug('DYVAM - Info: Injection mode. videoID '+(videoID?videoID:'none')+'; ');
  }
     
  if (videoID==null || videoTicket==null) { // if all else fails
    var bodyContent=document.body.innerHTML;  
    if (bodyContent!=null) {
      videoID=findMatch(bodyContent, /\"video_id\":\s*\"([^\"]+)\"/);
      videoTicket=findMatch(bodyContent, /\"t\":\s*\"([^\"]+)\"/);
      videoFormats=findMatch(bodyContent, /\"url_encoded_fmt_stream_map\":\s*\"([^\"]+)\"/);
      if (scriptURL==null) {
        scriptURL=findMatch(bodyContent, /\"js\":\s*\"([^\"]+)\"/);
        scriptURL=scriptURL.replace(/\\/g,'');
      }      
    }
    debug('DYVAM - Info: Brute mode. videoID '+(videoID?videoID:'none')+'; ');
  }
  
  debug('DYVAM - Info: url '+window.location.href+'; useragent '+window.navigator.userAgent);  
  
  if (videoID==null || videoTicket==null || videoFormats==null || videoID.length==0 || videoTicket.length==0 || videoFormats.length==0) {
   debug('DYVAM - Error: No config information found. YouTube must have changed the code.');
   return;
  }
   
  if (!isDecodeRuleUpdated) {
    DECODE_RULE=updateDecodeRules(DECODE_RULE);
    isDecodeRuleUpdated=true;
  }

  if (scriptURL) {
    fetchSignatureScript(scriptURL);
  }
  
   // video title
  var videoTitle=document.title || 'video';
  videoTitle=videoTitle.replace(/\s*\-\s*YouTube$/i,'').replace(/[#"\?:\*]/g,'').replace(/[&\|\\\/]/g,'_').replace(/'/g,'\'').replace(/^\s+|\s+$/g,'').replace(/\.+$/g,''); 
                        
  // parse the formats map
  var sep1='%2C', sep2='%26', sep3='%3D';
  if (videoFormats.indexOf(',')>-1) { 
    sep1=','; 
    sep2=(videoFormats.indexOf('&')>-1)?'&':'\\u0026'; 
    sep3='=';
  }  
  
  var videoURL=new Array();
  var videoSignature=new Array();
  var videoFormatsGroup=videoFormats.split(sep1);
  for (var i=0;i<videoFormatsGroup.length;i++) {
    var videoFormatsElem=videoFormatsGroup[i].split(sep2);
    var videoFormatsPair=new Array();
    for (var j=0;j<videoFormatsElem.length;j++) {
      var pair=videoFormatsElem[j].split(sep3);
      if (pair.length==2) {
        videoFormatsPair[pair[0]]=pair[1];
      }
    }
    if (videoFormatsPair['url']==null) continue;
    url=unescape(unescape(videoFormatsPair['url'])).replace(/\\\//g,'/').replace(/\\u0026/g,'&');
    if (videoFormatsPair['itag']==null) continue;
    itag=videoFormatsPair['itag'];
    if (videoFormatsPair['sig']) {
      url=url+'&signature='+videoFormatsPair['sig'];
      videoSignature[itag]=null;
    } else if (videoFormatsPair['s']) {
      url=url+'&signature='+decryptSignature(videoFormatsPair['s']);
      videoSignature[itag]=videoFormatsPair['s'];
    }
    if (url.toLowerCase().indexOf('http')==0) { // validate URL
      videoURL[itag]=url+'&title='+videoTitle;    
    }
  }
  
  var showFormat=new Array();
  for (var category in FORMAT_RULE) {
    var rule=FORMAT_RULE[category];
    for (var index in FORMAT_TYPE){
      if (FORMAT_TYPE[index]==category) {
        showFormat[index]=(rule=='all');
      }
    }
    if (rule=='max') {
      for (var i=FORMAT_ORDER.length-1;i>=0;i--) {
        var format=FORMAT_ORDER[i];
        if (FORMAT_TYPE[format]==category && videoURL[format]!=undefined) {
          showFormat[format]=true;
          break;
        }
      }
    }
  }
  
  var downloadCodeList=[];
  for (var i=0;i<FORMAT_ORDER.length;i++) {
    var format=FORMAT_ORDER[i];
    if (videoURL[format]!=undefined && FORMAT_LABEL[format]!=undefined && showFormat[format]) {
      downloadCodeList.push({url:videoURL[format],sig:videoSignature[format],format:format,label:FORMAT_LABEL[format]});
      debug('DYVAM - Info: itag'+format+' url:'+videoURL[format]);
    }
  }
  if (downloadCodeList.length==0) {
    debug('DYVAM - Error: No download URL found. Probably YouTube uses encrypted streams.');
    return; // no format
  } 
  
  // find parent container
  var parentElement=document.getElementById('watch7-action-buttons');
  if (parentElement==null) {
    debug('DYVAM - No container for adding the download button. YouTube must have changed the code.');
    return;
  }
  
  // get button labels
  var buttonText=(BUTTON_TEXT[language])?BUTTON_TEXT[language]:BUTTON_TEXT['en'];
  var buttonLabel=(BUTTON_TOOLTIP[language])?BUTTON_TOOLTIP[language]:BUTTON_TOOLTIP['en'];
    
  // generate download code for regular interface
  var mainSpan=document.createElement('span');
  var spanButton=document.createElement('span');
  spanButton.setAttribute('class', 'yt-uix-button-content');
  spanButton.appendChild(document.createTextNode(buttonText+' '));
  mainSpan.appendChild(spanButton);
  var imgButton=document.createElement('img');
  imgButton.setAttribute('class', 'yt-uix-button-arrow');
  imgButton.setAttribute('src', '//s.ytimg.com/yt/img/pixel-vfl3z5WfW.gif');
  mainSpan.appendChild(imgButton);
  var listItems=document.createElement('ol');
  listItems.setAttribute('style', 'display:none;');
  listItems.setAttribute('class', 'yt-uix-button-menu');
  for (var i=0;i<downloadCodeList.length;i++) {
    var listItem=document.createElement('li');
    var listLink=document.createElement('a');
    listLink.setAttribute('style', 'text-decoration:none;');
    listLink.setAttribute('href', downloadCodeList[i].url);
    var listSpan=document.createElement('span');
    listSpan.setAttribute('class', 'yt-uix-button-menu-item');
    listSpan.setAttribute('loop', i+'');
    listSpan.setAttribute('id', LISTITEM_ID+downloadCodeList[i].format);
    listSpan.appendChild(document.createTextNode(downloadCodeList[i].label));
    listLink.appendChild(listSpan);
    listItem.appendChild(listLink);
    listItems.appendChild(listItem);    
  }
  mainSpan.appendChild(listItems);
  var buttonElement=document.createElement('button');
  buttonElement.setAttribute('id', BUTTON_ID);
  buttonElement.setAttribute('class', 'yt-uix-button yt-uix-tooltip yt-uix-button-empty yt-uix-button-text');
  buttonElement.setAttribute('style', 'margin-top:4px; margin-left:'+((textDirection=='left')?5:10)+'px;');
  buttonElement.setAttribute('data-tooltip-text', buttonLabel);
  buttonElement.addEventListener('click', function(){return false;}, false);
  buttonElement.setAttribute('type', 'button');
  buttonElement.setAttribute('role', 'button');
  buttonElement.appendChild(mainSpan);
                                            
  // add the button
  var containerSpan=document.createElement('span');
  containerSpan.setAttribute('id', CONTAINER_ID);      
  containerSpan.appendChild(document.createTextNode(' '));
  containerSpan.appendChild(buttonElement);
  parentElement.appendChild(containerSpan);
    
  for (var i=0;i<downloadCodeList.length;i++) {
    var downloadFMT=document.getElementById(LISTITEM_ID+downloadCodeList[i].format);
    downloadFMT.addEventListener('click', downloadVideo, false);
  }
  
  // KrISS dwld
  function kriss_dwld() {
    var s = '';
    for (var i=0;i<window.kriss_dwld.length;i++) {
        s += (i+1) +' => '+window.kriss_dwld[i].label+'\n';
    }
    var r = parseInt(window.prompt(s),10)-1;
    if (r < window.kriss_dwld.length) {
      window.location.assign(window.kriss_dwld[r].url);
    }
  }

  window.kriss_dwld = downloadCodeList.slice();

  kriss_dwld();

  function downloadVideo(e) {
    var elem=e.target;
    e.returnValue=false;
    if (e.preventDefault) {
      e.preventDefault();
    }
    var url=downloadCodeList[elem.getAttribute('loop')].url;    
    if (isSignatureCodeUpdated) {      
      var sig=downloadCodeList[elem.getAttribute('loop')].sig;
      if (sig) {
        sig=decryptSignature(sig);
        url=url.replace(/\&signature=[\w\.]+/, '&signature='+sig);
      }
    }
    window.location.href=url;
    return false;
  }
  
  function injectStyle(code) {
    var style=document.createElement('style');
    style.type='text/css';
    style.appendChild(document.createTextNode(code));
    document.getElementsByTagName('head')[0].appendChild(style);
  }
  
  function injectScript(code) {
    var script=document.createElement('script');
    script.type='application/javascript';
    script.textContent=code;
    document.body.appendChild(script);
    document.body.removeChild(script);
  }  
  
  function getUnsafeWindow() { // Firefox, Opera<15
    return (typeof this.unsafeWindow !== 'undefined')?this.unsafeWindow:window;
  } 
  
  function debug(str) {
    var debugElem=document.getElementById(DEBUG_ID);
    if (!debugElem) {
      debugElem=createHiddenElem('div', DEBUG_ID);
    }
    debugElem.appendChild(document.createTextNode(str+' '));
  }
  
  function createHiddenElem(tag, id) {
    var elem=document.createElement(tag);
    elem.setAttribute('id', id);
    elem.setAttribute('style', 'display:none;');
    document.body.appendChild(elem);
    return elem;
  }
  
  function findLanguage() {
    var language=document.documentElement.getAttribute('lang');
    if (language=='') {
        var footer=document.getElementById('');
        if (footer && /\/ja\/|\.co\.jp/.test(footer.innerHTML)) {
          language='ja'; // fix YouTube bug
        }
    }
    return language;
  }
  
  function fixTranslations(language, textDirection) {  
    var styleCode='#watch7-sentiment-actions div.share-panel-services{display:none!important}';
    if (/^af|bg|bn|ca|cs|de|el|es|et|eu|fa|fi|fil|fr|gl|hi|hr|hu|id|it|iw|kn|lv|lt|ml|mr|ms|nl|pl|pt|ro|ru|sl|sk|sr|sw|ta|te|th|uk|ur|vi|zu$/.test(language)) { // fix international UI
      var likeButton=document.getElementById('watch-like');
      if (likeButton) {
        var spanElements=likeButton.getElementsByClassName('yt-uix-button-content');
        if (spanElements) {
          spanElements[0].style.display='none'; // hide like text
        }
      }
      var marginPixels=10;
      if (/^bg|ca|cs|el|es|eu|fr|hr|it|ml|ms|pl|ro|ru|sl|sw|te$/.test(language)) {
        marginPixels=1;
      }
      styleCode+=' #watch7-secondary-actions .yt-uix-button{margin-'+textDirection+':'+marginPixels+'px!important}';
    }
    injectStyle(styleCode);
  }
  
  function findMatch(text, regexp) {
      var matches=text.match(regexp);
      return (matches)?matches[1]:null;
  }
  
  function isString(s) {
    return (typeof s==='string' || s instanceof String);
  }
    
  function isInteger(n) {
    return (typeof n==='number' && n%1==0);
  }
  
  function getPref(name) { // cross-browser GM_getValue
    if (typeof GM_getValue === 'function' && 
    (typeof GM_getValue.toString === 'undefined' || GM_getValue.toString().indexOf('not supported') === -1)) {
      return GM_getValue(name, null); // Greasemonkey, Tampermonkey, Firefox extension
    } else {
        var ls=null;
        try {ls=window.localStorage||null} catch(e){}
        if (ls) {
          return ls.getItem(name); // Chrome script, Opera extensions
        }
    }
    return;
  }
  
  function setPref(name, value) { //  cross-browser GM_setValue
    if (typeof GM_setValue === 'function' && 
    (typeof GM_setValue.toString === 'undefined' || GM_setValue.toString().indexOf('not supported') === -1)) {
      GM_setValue(name, value); // Greasemonkey, Tampermonkey, Firefox extension
    } else {
        var ls=null;
        try {ls=window.localStorage||null} catch(e){}
        if (ls) {
          return ls.setItem(name, value); // Chrome script, Opera extensions
        }
    }
  }
  
  function findSignatureCode(sourceCode) {
    var functionName=findMatch(sourceCode, /\.signature\s*=\s*(\w+)\(\w+\)/);
    if (functionName==null) return setPref(STORAGE_CODE, 'error');
    var regCode=new RegExp('function '+functionName+
    '\\s*\\(\\w+\\)\\s*{\\w+=\\w+\\.split\\(""\\);(.+);return \\w+\\.join');
    var functionCode=findMatch(sourceCode, regCode);
    if (functionCode==null) return setPref(STORAGE_CODE, 'error');
    var regSlice=new RegExp('slice\\s*\\(\\s*(.+)\\s*\\)');
    var regSwap=new RegExp('\\w+\\s*\\(\\s*\\w+\\s*,\\s*([0-9]+)\\s*\\)');
    var regInline=new RegExp('\\w+\\[0\\]\\s*=\\s*\\w+\\[([0-9]+)\\s*%\\s*\\w+\\.length\\]');    var functionCodePieces=functionCode.split(';');
    var decodeArray=[], signatureLength=81;
    for (var i=0; i<functionCodePieces.length; i++) {
      functionCodePieces[i]=functionCodePieces[i].trim();
      if (functionCodePieces[i].length==0) {
      } else if (functionCodePieces[i].indexOf('slice') >= 0) { // slice
        var slice=findMatch(functionCodePieces[i], regSlice);
        slice=parseInt(slice, 10);
        if (isInteger(slice)){ 
          decodeArray.push(-slice);
          signatureLength+=slice;
        } else return setPref(STORAGE_CODE, 'error');
      } else if (functionCodePieces[i].indexOf('reverse') >= 0) {
        decodeArray.push(0);
      } else if (functionCodePieces[i].indexOf('[0]') >= 0) {
          if (i+2<functionCodePieces.length &&
          functionCodePieces[i+1].indexOf('.length') >= 0 &&
          functionCodePieces[i+1].indexOf('[0]') >= 0) {
            var inline=findMatch(functionCodePieces[i+1], regInline);
            inline=parseInt(inline, 10);
            decodeArray.push(inline);
            i+=2;
          } else return setPref(STORAGE_CODE, 'error');
      } else if (functionCodePieces[i].indexOf(',') >= 0) {
        var swap=findMatch(functionCodePieces[i], regSwap);      
        swap=parseInt(swap, 10);
        if (isInteger(swap)){
          decodeArray.push(swap);
        } else return setPref(STORAGE_CODE, 'error');
      } else return setPref(STORAGE_CODE, 'error');
    }
    
    if (decodeArray) {
      setPref(STORAGE_URL, scriptURL);
      setPref(STORAGE_CODE, decodeArray.toString());
      DECODE_RULE[signatureLength]=decodeArray;
      isSignatureCodeUpdated=true; // updated signature
    }
  }
  
  function isValidSignatureCode(arr) { // valid values: '5,-3,0,2,5', 'error'
    if (!arr) return false;
    if (arr=='error') return true;
    arr=arr.split(',');
    for (var i=0;i<arr.length;i++) {
      if (!isInteger(parseInt(arr[i],10))) return false;
    }
    return true;
  }
  
  function fetchSignatureScript(scriptURL) {
    var storageURL=getPref(STORAGE_URL);
    var storageCode=getPref(STORAGE_CODE);
    if (storageCode && isValidSignatureCode(storageCode) && storageURL &&
        scriptURL.replace(/^https?/i,'')==storageURL.replace(/^https?/i,'')) return;
        findSignatureCode(<?php echo json_encode(file_get_contents(urldecode($_GET['url']))); ?>);
  }
  
  function updateDecodeRules(rules) {
    var storageCode=getPref(STORAGE_CODE);    
    if (storageCode && storageCode!='error' && isValidSignatureCode(storageCode)) {
      var arr=storageCode.split(',');
      var signatureLength=81;
      for (var i=0; i<arr.length; i++) {
        arr[i]=parseInt(arr[i], 10);
        if (arr[i]<0) signatureLength-=arr[i];
      }
      rules[signatureLength]=arr;
    }
    return rules;
  }
  
  function decryptSignature(sig) {
    function swap(a,b){var c=a[0];a[0]=a[b%a.length];a[b]=c;return a};
    function decode(sig, arr) { // encoded decryption
      if (!isString(sig)) return null;
      var sigA=sig.split('');
      for (var i=0;i<arr.length;i++) {
        var act=arr[i];
        if (!isInteger(act)) return null;
        sigA=(act>0)?swap(sigA, act):((act==0)?sigA.reverse():sigA.slice(-act));
      }
      var result=sigA.join('');
      return (result.length==81)?result:sig;
    }
    
    if (sig==null) return '';    
    var arr=DECODE_RULE[sig.length];
    if (arr) {
      var sig2=decode(sig, arr);
      if (sig2 && sig2.length==81) return sig2;
    }
    return sig; 
  }
 }
})();


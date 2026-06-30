/*!
FullCalendar Core Package v4.4.2
Docs & License: https://fullcalendar.io/
(c) 2019 Adam Shaw
*/(function(global,factory){typeof exports==='object'&&typeof module!=='undefined'?factory(exports):typeof define==='function'&&define.amd?define(['exports'],factory):(global=global||self,factory(global.FullCalendar={}));}(this,function(exports){'use strict';var elementPropHash={className:true,colSpan:true,rowSpan:true};var containerTagHash={'<tr':'tbody','<td':'tr'};function createElement(tagName,attrs,content){var el=document.createElement(tagName);if(attrs){for(var attrName in attrs){if(attrName==='style'){applyStyle(el,attrs[attrName]);}
else if(elementPropHash[attrName]){el[attrName]=attrs[attrName];}
else{el.setAttribute(attrName,attrs[attrName]);}}}
if(typeof content==='string'){el.innerHTML=content;}
else if(content!=null){appendToElement(el,content);}
return el;}
function htmlToElement(html){html=html.trim();var container=document.createElement(computeContainerTag(html));container.innerHTML=html;return container.firstChild;}
function htmlToElements(html){return Array.prototype.slice.call(htmlToNodeList(html));}
function htmlToNodeList(html){html=html.trim();var container=document.createElement(computeContainerTag(html));container.innerHTML=html;return container.childNodes;}
function computeContainerTag(html){return containerTagHash[html.substr(0,3)]||'div';}
function appendToElement(el,content){var childNodes=normalizeContent(content);for(var i=0;i<childNodes.length;i++){el.appendChild(childNodes[i]);}}
function prependToElement(parent,content){var newEls=normalizeContent(content);var afterEl=parent.firstChild||null;for(var i=0;i<newEls.length;i++){parent.insertBefore(newEls[i],afterEl);}}
function insertAfterElement(refEl,content){var newEls=normalizeContent(content);var afterEl=refEl.nextSibling||null;for(var i=0;i<newEls.length;i++){refEl.parentNode.insertBefore(newEls[i],afterEl);}}
function normalizeContent(content){var els;if(typeof content==='string'){els=htmlToElements(content);}
else if(content instanceof Node){els=[content];}
else{els=Array.prototype.slice.call(content);}
return els;}
function removeElement(el){if(el.parentNode){el.parentNode.removeChild(el);}}
var matchesMethod=Element.prototype.matches||Element.prototype.matchesSelector||Element.prototype.msMatchesSelector;var closestMethod=Element.prototype.closest||function(selector){var el=this;if(!document.documentElement.contains(el)){return null;}
do{if(elementMatches(el,selector)){return el;}
el=el.parentElement||el.parentNode;}while(el!==null&&el.nodeType===1);return null;};function elementClosest(el,selector){return closestMethod.call(el,selector);}
function elementMatches(el,selector){return matchesMethod.call(el,selector);}
function findElements(container,selector){var containers=container instanceof HTMLElement?[container]:container;var allMatches=[];for(var i=0;i<containers.length;i++){var matches=containers[i].querySelectorAll(selector);for(var j=0;j<matches.length;j++){allMatches.push(matches[j]);}}
return allMatches;}
function findChildren(parent,selector){var parents=parent instanceof HTMLElement?[parent]:parent;var allMatches=[];for(var i=0;i<parents.length;i++){var childNodes=parents[i].children;for(var j=0;j<childNodes.length;j++){var childNode=childNodes[j];if(!selector||elementMatches(childNode,selector)){allMatches.push(childNode);}}}
return allMatches;}
function forceClassName(el,className,bool){if(bool){el.classList.add(className);}
else{el.classList.remove(className);}}
var PIXEL_PROP_RE=/(top|left|right|bottom|width|height)$/i;function applyStyle(el,props){for(var propName in props){applyStyleProp(el,propName,props[propName]);}}
function applyStyleProp(el,name,val){if(val==null){el.style[name]='';}
else if(typeof val==='number'&&PIXEL_PROP_RE.test(name)){el.style[name]=val+'px';}
else{el.style[name]=val;}}
function pointInsideRect(point,rect){return point.left>=rect.left&&point.left<rect.right&&point.top>=rect.top&&point.top<rect.bottom;}
function intersectRects(rect1,rect2){var res={left:Math.max(rect1.left,rect2.left),right:Math.min(rect1.right,rect2.right),top:Math.max(rect1.top,rect2.top),bottom:Math.min(rect1.bottom,rect2.bottom)};if(res.left<res.right&&res.top<res.bottom){return res;}
return false;}
function translateRect(rect,deltaX,deltaY){return{left:rect.left+deltaX,right:rect.right+deltaX,top:rect.top+deltaY,bottom:rect.bottom+deltaY};}
function constrainPoint(point,rect){return{left:Math.min(Math.max(point.left,rect.left),rect.right),top:Math.min(Math.max(point.top,rect.top),rect.bottom)};}
function getRectCenter(rect){return{left:(rect.left+rect.right)/2,top:(rect.top+rect.bottom)/2};}
function diffPoints(point1,point2){return{left:point1.left-point2.left,top:point1.top-point2.top};}
var isRtlScrollbarOnLeft=null;function getIsRtlScrollbarOnLeft(){if(isRtlScrollbarOnLeft===null){isRtlScrollbarOnLeft=computeIsRtlScrollbarOnLeft();}
return isRtlScrollbarOnLeft;}
function computeIsRtlScrollbarOnLeft(){var outerEl=createElement('div',{style:{position:'absolute',top:-1000,left:0,border:0,padding:0,overflow:'scroll',direction:'rtl'}},'<div></div>');document.body.appendChild(outerEl);var innerEl=outerEl.firstChild;var res=innerEl.getBoundingClientRect().left>outerEl.getBoundingClientRect().left;removeElement(outerEl);return res;}
function sanitizeScrollbarWidth(width){width=Math.max(0,width);width=Math.round(width);return width;}
function computeEdges(el,getPadding){if(getPadding===void 0){getPadding=false;}
var computedStyle=window.getComputedStyle(el);var borderLeft=parseInt(computedStyle.borderLeftWidth,10)||0;var borderRight=parseInt(computedStyle.borderRightWidth,10)||0;var borderTop=parseInt(computedStyle.borderTopWidth,10)||0;var borderBottom=parseInt(computedStyle.borderBottomWidth,10)||0;var scrollbarLeftRight=sanitizeScrollbarWidth(el.offsetWidth-el.clientWidth-borderLeft-borderRight);var scrollbarBottom=sanitizeScrollbarWidth(el.offsetHeight-el.clientHeight-borderTop-borderBottom);var res={borderLeft:borderLeft,borderRight:borderRight,borderTop:borderTop,borderBottom:borderBottom,scrollbarBottom:scrollbarBottom,scrollbarLeft:0,scrollbarRight:0};if(getIsRtlScrollbarOnLeft()&&computedStyle.direction==='rtl'){res.scrollbarLeft=scrollbarLeftRight;}
else{res.scrollbarRight=scrollbarLeftRight;}
if(getPadding){res.paddingLeft=parseInt(computedStyle.paddingLeft,10)||0;res.paddingRight=parseInt(computedStyle.paddingRight,10)||0;res.paddingTop=parseInt(computedStyle.paddingTop,10)||0;res.paddingBottom=parseInt(computedStyle.paddingBottom,10)||0;}
return res;}
function computeInnerRect(el,goWithinPadding){if(goWithinPadding===void 0){goWithinPadding=false;}
var outerRect=computeRect(el);var edges=computeEdges(el,goWithinPadding);var res={left:outerRect.left+edges.borderLeft+edges.scrollbarLeft,right:outerRect.right-edges.borderRight-edges.scrollbarRight,top:outerRect.top+edges.borderTop,bottom:outerRect.bottom-edges.borderBottom-edges.scrollbarBottom};if(goWithinPadding){res.left+=edges.paddingLeft;res.right-=edges.paddingRight;res.top+=edges.paddingTop;res.bottom-=edges.paddingBottom;}
return res;}
function computeRect(el){var rect=el.getBoundingClientRect();return{left:rect.left+window.pageXOffset,top:rect.top+window.pageYOffset,right:rect.right+window.pageXOffset,bottom:rect.bottom+window.pageYOffset};}
function computeViewportRect(){return{left:window.pageXOffset,right:window.pageXOffset+document.documentElement.clientWidth,top:window.pageYOffset,bottom:window.pageYOffset+document.documentElement.clientHeight};}
function computeHeightAndMargins(el){return el.getBoundingClientRect().height+computeVMargins(el);}
function computeVMargins(el){var computed=window.getComputedStyle(el);return parseInt(computed.marginTop,10)+
parseInt(computed.marginBottom,10);}
function getClippingParents(el){var parents=[];while(el instanceof HTMLElement){var computedStyle=window.getComputedStyle(el);if(computedStyle.position==='fixed'){break;}
if((/(auto|scroll)/).test(computedStyle.overflow+computedStyle.overflowY+computedStyle.overflowX)){parents.push(el);}
el=el.parentNode;}
return parents;}
function computeClippingRect(el){return getClippingParents(el).map(function(el){return computeInnerRect(el);}).concat(computeViewportRect()).reduce(function(rect0,rect1){return intersectRects(rect0,rect1)||rect1;});}
function preventDefault(ev){ev.preventDefault();}
function listenBySelector(container,eventType,selector,handler){function realHandler(ev){var matchedChild=elementClosest(ev.target,selector);if(matchedChild){handler.call(matchedChild,ev,matchedChild);}}
container.addEventListener(eventType,realHandler);return function(){container.removeEventListener(eventType,realHandler);};}
function listenToHoverBySelector(container,selector,onMouseEnter,onMouseLeave){var currentMatchedChild;return listenBySelector(container,'mouseover',selector,function(ev,matchedChild){if(matchedChild!==currentMatchedChild){currentMatchedChild=matchedChild;onMouseEnter(ev,matchedChild);var realOnMouseLeave_1=function(ev){currentMatchedChild=null;onMouseLeave(ev,matchedChild);matchedChild.removeEventListener('mouseleave',realOnMouseLeave_1);};matchedChild.addEventListener('mouseleave',realOnMouseLeave_1);}});}
var transitionEventNames=['webkitTransitionEnd','otransitionend','oTransitionEnd','msTransitionEnd','transitionend'];function whenTransitionDone(el,callback){var realCallback=function(ev){callback(ev);transitionEventNames.forEach(function(eventName){el.removeEventListener(eventName,realCallback);});};transitionEventNames.forEach(function(eventName){el.addEventListener(eventName,realCallback);});}
var DAY_IDS=['sun','mon','tue','wed','thu','fri','sat'];function addWeeks(m,n){var a=dateToUtcArray(m);a[2]+=n*7;return arrayToUtcDate(a);}
function addDays(m,n){var a=dateToUtcArray(m);a[2]+=n;return arrayToUtcDate(a);}
function addMs(m,n){var a=dateToUtcArray(m);a[6]+=n;return arrayToUtcDate(a);}
function diffWeeks(m0,m1){return diffDays(m0,m1)/7;}
function diffDays(m0,m1){return(m1.valueOf()-m0.valueOf())/(1000*60*60*24);}
function diffHours(m0,m1){return(m1.valueOf()-m0.valueOf())/(1000*60*60);}
function diffMinutes(m0,m1){return(m1.valueOf()-m0.valueOf())/(1000*60);}
function diffSeconds(m0,m1){return(m1.valueOf()-m0.valueOf())/1000;}
function diffDayAndTime(m0,m1){var m0day=startOfDay(m0);var m1day=startOfDay(m1);return{years:0,months:0,days:Math.round(diffDays(m0day,m1day)),milliseconds:(m1.valueOf()-m1day.valueOf())-(m0.valueOf()-m0day.valueOf())};}
function diffWholeWeeks(m0,m1){var d=diffWholeDays(m0,m1);if(d!==null&&d%7===0){return d/7;}
return null;}
function diffWholeDays(m0,m1){if(timeAsMs(m0)===timeAsMs(m1)){return Math.round(diffDays(m0,m1));}
return null;}
function startOfDay(m){return arrayToUtcDate([m.getUTCFullYear(),m.getUTCMonth(),m.getUTCDate()]);}
function startOfHour(m){return arrayToUtcDate([m.getUTCFullYear(),m.getUTCMonth(),m.getUTCDate(),m.getUTCHours()]);}
function startOfMinute(m){return arrayToUtcDate([m.getUTCFullYear(),m.getUTCMonth(),m.getUTCDate(),m.getUTCHours(),m.getUTCMinutes()]);}
function startOfSecond(m){return arrayToUtcDate([m.getUTCFullYear(),m.getUTCMonth(),m.getUTCDate(),m.getUTCHours(),m.getUTCMinutes(),m.getUTCSeconds()]);}
function weekOfYear(marker,dow,doy){var y=marker.getUTCFullYear();var w=weekOfGivenYear(marker,y,dow,doy);if(w<1){return weekOfGivenYear(marker,y-1,dow,doy);}
var nextW=weekOfGivenYear(marker,y+1,dow,doy);if(nextW>=1){return Math.min(w,nextW);}
return w;}
function weekOfGivenYear(marker,year,dow,doy){var firstWeekStart=arrayToUtcDate([year,0,1+firstWeekOffset(year,dow,doy)]);var dayStart=startOfDay(marker);var days=Math.round(diffDays(firstWeekStart,dayStart));return Math.floor(days/7)+1;}
function firstWeekOffset(year,dow,doy){var fwd=7+dow-doy;var fwdlw=(7+arrayToUtcDate([year,0,fwd]).getUTCDay()-dow)%7;return-fwdlw+fwd-1;}
function dateToLocalArray(date){return[date.getFullYear(),date.getMonth(),date.getDate(),date.getHours(),date.getMinutes(),date.getSeconds(),date.getMilliseconds()];}
function arrayToLocalDate(a){return new Date(a[0],a[1]||0,a[2]==null?1:a[2],a[3]||0,a[4]||0,a[5]||0);}
function dateToUtcArray(date){return[date.getUTCFullYear(),date.getUTCMonth(),date.getUTCDate(),date.getUTCHours(),date.getUTCMinutes(),date.getUTCSeconds(),date.getUTCMilliseconds()];}
function arrayToUtcDate(a){if(a.length===1){a=a.concat([0]);}
return new Date(Date.UTC.apply(Date,a));}
function isValidDate(m){return!isNaN(m.valueOf());}
function timeAsMs(m){return m.getUTCHours()*1000*60*60+
m.getUTCMinutes()*1000*60+
m.getUTCSeconds()*1000+
m.getUTCMilliseconds();}
var INTERNAL_UNITS=['years','months','days','milliseconds'];var PARSE_RE=/^(-?)(?:(\d+)\.)?(\d+):(\d\d)(?::(\d\d)(?:\.(\d\d\d))?)?/;function createDuration(input,unit){var _a;if(typeof input==='string'){return parseString(input);}
else if(typeof input==='object'&&input){return normalizeObject(input);}
else if(typeof input==='number'){return normalizeObject((_a={},_a[unit||'milliseconds']=input,_a));}
else{return null;}}
function parseString(s){var m=PARSE_RE.exec(s);if(m){var sign=m[1]?-1:1;return{years:0,months:0,days:sign*(m[2]?parseInt(m[2],10):0),milliseconds:sign*((m[3]?parseInt(m[3],10):0)*60*60*1000+
(m[4]?parseInt(m[4],10):0)*60*1000+
(m[5]?parseInt(m[5],10):0)*1000+
(m[6]?parseInt(m[6],10):0))};}
return null;}
function normalizeObject(obj){return{years:obj.years||obj.year||0,months:obj.months||obj.month||0,days:(obj.days||obj.day||0)+
getWeeksFromInput(obj)*7,milliseconds:(obj.hours||obj.hour||0)*60*60*1000+
(obj.minutes||obj.minute||0)*60*1000+
(obj.seconds||obj.second||0)*1000+
(obj.milliseconds||obj.millisecond||obj.ms||0)};}
function getWeeksFromInput(obj){return obj.weeks||obj.week||0;}
function durationsEqual(d0,d1){return d0.years===d1.years&&d0.months===d1.months&&d0.days===d1.days&&d0.milliseconds===d1.milliseconds;}
function isSingleDay(dur){return dur.years===0&&dur.months===0&&dur.days===1&&dur.milliseconds===0;}
function addDurations(d0,d1){return{years:d0.years+d1.years,months:d0.months+d1.months,days:d0.days+d1.days,milliseconds:d0.milliseconds+d1.milliseconds};}
function subtractDurations(d1,d0){return{years:d1.years-d0.years,months:d1.months-d0.months,days:d1.days-d0.days,milliseconds:d1.milliseconds-d0.milliseconds};}
function multiplyDuration(d,n){return{years:d.years*n,months:d.months*n,days:d.days*n,milliseconds:d.milliseconds*n};}
function asRoughYears(dur){return asRoughDays(dur)/365;}
function asRoughMonths(dur){return asRoughDays(dur)/30;}
function asRoughDays(dur){return asRoughMs(dur)/864e5;}
function asRoughMinutes(dur){return asRoughMs(dur)/(1000*60);}
function asRoughSeconds(dur){return asRoughMs(dur)/1000;}
function asRoughMs(dur){return dur.years*(365*864e5)+
dur.months*(30*864e5)+
dur.days*864e5+
dur.milliseconds;}
function wholeDivideDurations(numerator,denominator){var res=null;for(var i=0;i<INTERNAL_UNITS.length;i++){var unit=INTERNAL_UNITS[i];if(denominator[unit]){var localRes=numerator[unit]/denominator[unit];if(!isInt(localRes)||(res!==null&&res!==localRes)){return null;}
res=localRes;}
else if(numerator[unit]){return null;}}
return res;}
function greatestDurationDenominator(dur,dontReturnWeeks){var ms=dur.milliseconds;if(ms){if(ms%1000!==0){return{unit:'millisecond',value:ms};}
if(ms%(1000*60)!==0){return{unit:'second',value:ms/1000};}
if(ms%(1000*60*60)!==0){return{unit:'minute',value:ms/(1000*60)};}
if(ms){return{unit:'hour',value:ms/(1000*60*60)};}}
if(dur.days){if(!dontReturnWeeks&&dur.days%7===0){return{unit:'week',value:dur.days/7};}
return{unit:'day',value:dur.days};}
if(dur.months){return{unit:'month',value:dur.months};}
if(dur.years){return{unit:'year',value:dur.years};}
return{unit:'millisecond',value:0};}
function compensateScroll(rowEl,scrollbarWidths){if(scrollbarWidths.left){applyStyle(rowEl,{borderLeftWidth:1,marginLeft:scrollbarWidths.left-1});}
if(scrollbarWidths.right){applyStyle(rowEl,{borderRightWidth:1,marginRight:scrollbarWidths.right-1});}}
function uncompensateScroll(rowEl){applyStyle(rowEl,{marginLeft:'',marginRight:'',borderLeftWidth:'',borderRightWidth:''});}
function disableCursor(){document.body.classList.add('fc-not-allowed');}
function enableCursor(){document.body.classList.remove('fc-not-allowed');}
function distributeHeight(els,availableHeight,shouldRedistribute){var minOffset1=Math.floor(availableHeight/els.length);var minOffset2=Math.floor(availableHeight-minOffset1*(els.length-1));var flexEls=[];var flexOffsets=[];var flexHeights=[];var usedHeight=0;undistributeHeight(els);els.forEach(function(el,i){var minOffset=i===els.length-1?minOffset2:minOffset1;var naturalHeight=el.getBoundingClientRect().height;var naturalOffset=naturalHeight+computeVMargins(el);if(naturalOffset<minOffset){flexEls.push(el);flexOffsets.push(naturalOffset);flexHeights.push(naturalHeight);}
else{usedHeight+=naturalOffset;}});if(shouldRedistribute){availableHeight-=usedHeight;minOffset1=Math.floor(availableHeight/flexEls.length);minOffset2=Math.floor(availableHeight-minOffset1*(flexEls.length-1));}
flexEls.forEach(function(el,i){var minOffset=i===flexEls.length-1?minOffset2:minOffset1;var naturalOffset=flexOffsets[i];var naturalHeight=flexHeights[i];var newHeight=minOffset-(naturalOffset-naturalHeight);if(naturalOffset<minOffset){el.style.height=newHeight+'px';}});}
function undistributeHeight(els){els.forEach(function(el){el.style.height='';});}
function matchCellWidths(els){var maxInnerWidth=0;els.forEach(function(el){var innerEl=el.firstChild;if(innerEl instanceof HTMLElement){var innerWidth_1=innerEl.getBoundingClientRect().width;if(innerWidth_1>maxInnerWidth){maxInnerWidth=innerWidth_1;}}});maxInnerWidth++;els.forEach(function(el){el.style.width=maxInnerWidth+'px';});return maxInnerWidth;}
function subtractInnerElHeight(outerEl,innerEl){var reflowStyleProps={position:'relative',left:-1};applyStyle(outerEl,reflowStyleProps);applyStyle(innerEl,reflowStyleProps);var diff=outerEl.getBoundingClientRect().height-
innerEl.getBoundingClientRect().height;var resetStyleProps={position:'',left:''};applyStyle(outerEl,resetStyleProps);applyStyle(innerEl,resetStyleProps);return diff;}
function preventSelection(el){el.classList.add('fc-unselectable');el.addEventListener('selectstart',preventDefault);}
function allowSelection(el){el.classList.remove('fc-unselectable');el.removeEventListener('selectstart',preventDefault);}
function preventContextMenu(el){el.addEventListener('contextmenu',preventDefault);}
function allowContextMenu(el){el.removeEventListener('contextmenu',preventDefault);}
function parseFieldSpecs(input){var specs=[];var tokens=[];var i;var token;if(typeof input==='string'){tokens=input.split(/\s*,\s*/);}
else if(typeof input==='function'){tokens=[input];}
else if(Array.isArray(input)){tokens=input;}
for(i=0;i<tokens.length;i++){token=tokens[i];if(typeof token==='string'){specs.push(token.charAt(0)==='-'?{field:token.substring(1),order:-1}:{field:token,order:1});}
else if(typeof token==='function'){specs.push({func:token});}}
return specs;}
function compareByFieldSpecs(obj0,obj1,fieldSpecs){var i;var cmp;for(i=0;i<fieldSpecs.length;i++){cmp=compareByFieldSpec(obj0,obj1,fieldSpecs[i]);if(cmp){return cmp;}}
return 0;}
function compareByFieldSpec(obj0,obj1,fieldSpec){if(fieldSpec.func){return fieldSpec.func(obj0,obj1);}
return flexibleCompare(obj0[fieldSpec.field],obj1[fieldSpec.field])*(fieldSpec.order||1);}
function flexibleCompare(a,b){if(!a&&!b){return 0;}
if(b==null){return-1;}
if(a==null){return 1;}
if(typeof a==='string'||typeof b==='string'){return String(a).localeCompare(String(b));}
return a-b;}
function capitaliseFirstLetter(str){return str.charAt(0).toUpperCase()+str.slice(1);}
function padStart(val,len){var s=String(val);return '000'.substr(0,len-s.length)+s;}
function compareNumbers(a,b){return a-b;}
function isInt(n){return n%1===0;}
function applyAll(functions,thisObj,args){if(typeof functions==='function'){functions=[functions];}
if(functions){var i=void 0;var ret=void 0;for(i=0;i<functions.length;i++){ret=functions[i].apply(thisObj,args)||ret;}
return ret;}}
function firstDefined(){var args=[];for(var _i=0;_i<arguments.length;_i++){args[_i]=arguments[_i];}
for(var i=0;i<args.length;i++){if(args[i]!==undefined){return args[i];}}}
function debounce(func,wait){var timeout;var args;var context;var timestamp;var result;var later=function(){var last=new Date().valueOf()-timestamp;if(last<wait){timeout=setTimeout(later,wait-last);}
else{timeout=null;result=func.apply(context,args);context=args=null;}};return function(){context=this;args=arguments;timestamp=new Date().valueOf();if(!timeout){timeout=setTimeout(later,wait);}
return result;};}
function refineProps(rawProps,processors,defaults,leftoverProps){if(defaults===void 0){defaults={};}
var refined={};for(var key in processors){var processor=processors[key];if(rawProps[key]!==undefined){if(processor===Function){refined[key]=typeof rawProps[key]==='function'?rawProps[key]:null;}
else if(processor){refined[key]=processor(rawProps[key]);}
else{refined[key]=rawProps[key];}}
else if(defaults[key]!==undefined){refined[key]=defaults[key];}
else{if(processor===String){refined[key]='';}
else if(!processor||processor===Number||processor===Boolean||processor===Function){refined[key]=null;}
else{refined[key]=processor(null);}}}
if(leftoverProps){for(var key in rawProps){if(processors[key]===undefined){leftoverProps[key]=rawProps[key];}}}
return refined;}
function computeAlignedDayRange(timedRange){var dayCnt=Math.floor(diffDays(timedRange.start,timedRange.end))||1;var start=startOfDay(timedRange.start);var end=addDays(start,dayCnt);return{start:start,end:end};}
function computeVisibleDayRange(timedRange,nextDayThreshold){if(nextDayThreshold===void 0){nextDayThreshold=createDuration(0);}
var startDay=null;var endDay=null;if(timedRange.end){endDay=startOfDay(timedRange.end);var endTimeMS=timedRange.end.valueOf()-endDay.valueOf();if(endTimeMS&&endTimeMS>=asRoughMs(nextDayThreshold)){endDay=addDays(endDay,1);}}
if(timedRange.start){startDay=startOfDay(timedRange.start);if(endDay&&endDay<=startDay){endDay=addDays(startDay,1);}}
return{start:startDay,end:endDay};}
function isMultiDayRange(range){var visibleRange=computeVisibleDayRange(range);return diffDays(visibleRange.start,visibleRange.end)>1;}
function diffDates(date0,date1,dateEnv,largeUnit){if(largeUnit==='year'){return createDuration(dateEnv.diffWholeYears(date0,date1),'year');}
else if(largeUnit==='month'){return createDuration(dateEnv.diffWholeMonths(date0,date1),'month');}
else{return diffDayAndTime(date0,date1);}}/*! *****************************************************************************
Copyright (c) Microsoft Corporation.
Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted.
THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH
REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT,
INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM
LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR
OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR
PERFORMANCE OF THIS SOFTWARE.
***************************************************************************** */
var extendStatics=function(d,b){extendStatics=Object.setPrototypeOf||({__proto__:[]}instanceof Array&&function(d,b){d.__proto__=b;})||function(d,b){for(var p in b)if(b.hasOwnProperty(p))d[p]=b[p];};return extendStatics(d,b);};function __extends(d,b){extendStatics(d,b);function __(){this.constructor=d;}
d.prototype=b===null?Object.create(b):(__.prototype=b.prototype,new __());}
var __assign=function(){__assign=Object.assign||function __assign(t){for(var s,i=1,n=arguments.length;i<n;i++){s=arguments[i];for(var p in s)if(Object.prototype.hasOwnProperty.call(s,p))t[p]=s[p];}
return t;};return __assign.apply(this,arguments);};function parseRecurring(eventInput,allDayDefault,dateEnv,recurringTypes,leftovers){for(var i=0;i<recurringTypes.length;i++){var localLeftovers={};var parsed=recurringTypes[i].parse(eventInput,localLeftovers,dateEnv);if(parsed){var allDay=localLeftovers.allDay;delete localLeftovers.allDay;if(allDay==null){allDay=allDayDefault;if(allDay==null){allDay=parsed.allDayGuess;if(allDay==null){allDay=false;}}}
__assign(leftovers,localLeftovers);return{allDay:allDay,duration:parsed.duration,typeData:parsed.typeData,typeId:i};}}
return null;}
function expandRecurringRanges(eventDef,duration,framingRange,dateEnv,recurringTypes){var typeDef=recurringTypes[eventDef.recurringDef.typeId];var markers=typeDef.expand(eventDef.recurringDef.typeData,{start:dateEnv.subtract(framingRange.start,duration),end:framingRange.end},dateEnv);if(eventDef.allDay){markers=markers.map(startOfDay);}
return markers;}
var hasOwnProperty=Object.prototype.hasOwnProperty;function mergeProps(propObjs,complexProps){var dest={};var i;var name;var complexObjs;var j;var val;var props;if(complexProps){for(i=0;i<complexProps.length;i++){name=complexProps[i];complexObjs=[];for(j=propObjs.length-1;j>=0;j--){val=propObjs[j][name];if(typeof val==='object'&&val){complexObjs.unshift(val);}
else if(val!==undefined){dest[name]=val;break;}}
if(complexObjs.length){dest[name]=mergeProps(complexObjs);}}}
for(i=propObjs.length-1;i>=0;i--){props=propObjs[i];for(name in props){if(!(name in dest)){dest[name]=props[name];}}}
return dest;}
function filterHash(hash,func){var filtered={};for(var key in hash){if(func(hash[key],key)){filtered[key]=hash[key];}}
return filtered;}
function mapHash(hash,func){var newHash={};for(var key in hash){newHash[key]=func(hash[key],key);}
return newHash;}
function arrayToHash(a){var hash={};for(var _i=0,a_1=a;_i<a_1.length;_i++){var item=a_1[_i];hash[item]=true;}
return hash;}
function hashValuesToArray(obj){var a=[];for(var key in obj){a.push(obj[key]);}
return a;}
function isPropsEqual(obj0,obj1){for(var key in obj0){if(hasOwnProperty.call(obj0,key)){if(!(key in obj1)){return false;}}}
for(var key in obj1){if(hasOwnProperty.call(obj1,key)){if(obj0[key]!==obj1[key]){return false;}}}
return true;}
function parseEvents(rawEvents,sourceId,calendar,allowOpenRange){var eventStore=createEmptyEventStore();for(var _i=0,rawEvents_1=rawEvents;_i<rawEvents_1.length;_i++){var rawEvent=rawEvents_1[_i];var tuple=parseEvent(rawEvent,sourceId,calendar,allowOpenRange);if(tuple){eventTupleToStore(tuple,eventStore);}}
return eventStore;}
function eventTupleToStore(tuple,eventStore){if(eventStore===void 0){eventStore=createEmptyEventStore();}
eventStore.defs[tuple.def.defId]=tuple.def;if(tuple.instance){eventStore.instances[tuple.instance.instanceId]=tuple.instance;}
return eventStore;}
function expandRecurring(eventStore,framingRange,calendar){var dateEnv=calendar.dateEnv;var defs=eventStore.defs,instances=eventStore.instances;instances=filterHash(instances,function(instance){return!defs[instance.defId].recurringDef;});for(var defId in defs){var def=defs[defId];if(def.recurringDef){var duration=def.recurringDef.duration;if(!duration){duration=def.allDay?calendar.defaultAllDayEventDuration:calendar.defaultTimedEventDuration;}
var starts=expandRecurringRanges(def,duration,framingRange,calendar.dateEnv,calendar.pluginSystem.hooks.recurringTypes);for(var _i=0,starts_1=starts;_i<starts_1.length;_i++){var start=starts_1[_i];var instance=createEventInstance(defId,{start:start,end:dateEnv.add(start,duration)});instances[instance.instanceId]=instance;}}}
return{defs:defs,instances:instances};}
function getRelevantEvents(eventStore,instanceId){var instance=eventStore.instances[instanceId];if(instance){var def_1=eventStore.defs[instance.defId];var newStore=filterEventStoreDefs(eventStore,function(lookDef){return isEventDefsGrouped(def_1,lookDef);});newStore.defs[def_1.defId]=def_1;newStore.instances[instance.instanceId]=instance;return newStore;}
return createEmptyEventStore();}
function isEventDefsGrouped(def0,def1){return Boolean(def0.groupId&&def0.groupId===def1.groupId);}
function transformRawEvents(rawEvents,eventSource,calendar){var calEachTransform=calendar.opt('eventDataTransform');var sourceEachTransform=eventSource?eventSource.eventDataTransform:null;if(sourceEachTransform){rawEvents=transformEachRawEvent(rawEvents,sourceEachTransform);}
if(calEachTransform){rawEvents=transformEachRawEvent(rawEvents,calEachTransform);}
return rawEvents;}
function transformEachRawEvent(rawEvents,func){var refinedEvents;if(!func){refinedEvents=rawEvents;}
else{refinedEvents=[];for(var _i=0,rawEvents_2=rawEvents;_i<rawEvents_2.length;_i++){var rawEvent=rawEvents_2[_i];var refinedEvent=func(rawEvent);if(refinedEvent){refinedEvents.push(refinedEvent);}
else if(refinedEvent==null){refinedEvents.push(rawEvent);}}}
return refinedEvents;}
function createEmptyEventStore(){return{defs:{},instances:{}};}
function mergeEventStores(store0,store1){return{defs:__assign({},store0.defs,store1.defs),instances:__assign({},store0.instances,store1.instances)};}
function filterEventStoreDefs(eventStore,filterFunc){var defs=filterHash(eventStore.defs,filterFunc);var instances=filterHash(eventStore.instances,function(instance){return defs[instance.defId];});return{defs:defs,instances:instances};}
function parseRange(input,dateEnv){var start=null;var end=null;if(input.start){start=dateEnv.createMarker(input.start);}
if(input.end){end=dateEnv.createMarker(input.end);}
if(!start&&!end){return null;}
if(start&&end&&end<start){return null;}
return{start:start,end:end};}
function invertRanges(ranges,constraintRange){var invertedRanges=[];var start=constraintRange.start;var i;var dateRange;ranges.sort(compareRanges);for(i=0;i<ranges.length;i++){dateRange=ranges[i];if(dateRange.start>start){invertedRanges.push({start:start,end:dateRange.start});}
if(dateRange.end>start){start=dateRange.end;}}
if(start<constraintRange.end){invertedRanges.push({start:start,end:constraintRange.end});}
return invertedRanges;}
function compareRanges(range0,range1){return range0.start.valueOf()-range1.start.valueOf();}
function intersectRanges(range0,range1){var start=range0.start;var end=range0.end;var newRange=null;if(range1.start!==null){if(start===null){start=range1.start;}
else{start=new Date(Math.max(start.valueOf(),range1.start.valueOf()));}}
if(range1.end!=null){if(end===null){end=range1.end;}
else{end=new Date(Math.min(end.valueOf(),range1.end.valueOf()));}}
if(start===null||end===null||start<end){newRange={start:start,end:end};}
return newRange;}
function rangesEqual(range0,range1){return(range0.start===null?null:range0.start.valueOf())===(range1.start===null?null:range1.start.valueOf())&&(range0.end===null?null:range0.end.valueOf())===(range1.end===null?null:range1.end.valueOf());}
function rangesIntersect(range0,range1){return(range0.end===null||range1.start===null||range0.end>range1.start)&&(range0.start===null||range1.end===null||range0.start<range1.end);}
function rangeContainsRange(outerRange,innerRange){return(outerRange.start===null||(innerRange.start!==null&&innerRange.start>=outerRange.start))&&(outerRange.end===null||(innerRange.end!==null&&innerRange.end<=outerRange.end));}
function rangeContainsMarker(range,date){return(range.start===null||date>=range.start)&&(range.end===null||date<range.end);}
function constrainMarkerToRange(date,range){if(range.start!=null&&date<range.start){return range.start;}
if(range.end!=null&&date>=range.end){return new Date(range.end.valueOf()-1);}
return date;}
function removeExact(array,exactVal){var removeCnt=0;var i=0;while(i<array.length){if(array[i]===exactVal){array.splice(i,1);removeCnt++;}
else{i++;}}
return removeCnt;}
function isArraysEqual(a0,a1){var len=a0.length;var i;if(len!==a1.length){return false;}
for(i=0;i<len;i++){if(a0[i]!==a1[i]){return false;}}
return true;}
function memoize(workerFunc){var args;var res;return function(){if(!args||!isArraysEqual(args,arguments)){args=arguments;res=workerFunc.apply(this,arguments);}
return res;};}
function memoizeOutput(workerFunc,equalityFunc){var cachedRes=null;return function(){var newRes=workerFunc.apply(this,arguments);if(cachedRes===null||!(cachedRes===newRes||equalityFunc(cachedRes,newRes))){cachedRes=newRes;}
return cachedRes;};}
var EXTENDED_SETTINGS_AND_SEVERITIES={week:3,separator:0,omitZeroMinute:0,meridiem:0,omitCommas:0};var STANDARD_DATE_PROP_SEVERITIES={timeZoneName:7,era:6,year:5,month:4,day:2,weekday:2,hour:1,minute:1,second:1};var MERIDIEM_RE=/\s*([ap])\.?m\.?/i;var COMMA_RE=/,/g;var MULTI_SPACE_RE=/\s+/g;var LTR_RE=/\u200e/g;var UTC_RE=/UTC|GMT/;var NativeFormatter=(function(){function NativeFormatter(formatSettings){var standardDateProps={};var extendedSettings={};var severity=0;for(var name_1 in formatSettings){if(name_1 in EXTENDED_SETTINGS_AND_SEVERITIES){extendedSettings[name_1]=formatSettings[name_1];severity=Math.max(EXTENDED_SETTINGS_AND_SEVERITIES[name_1],severity);}
else{standardDateProps[name_1]=formatSettings[name_1];if(name_1 in STANDARD_DATE_PROP_SEVERITIES){severity=Math.max(STANDARD_DATE_PROP_SEVERITIES[name_1],severity);}}}
this.standardDateProps=standardDateProps;this.extendedSettings=extendedSettings;this.severity=severity;this.buildFormattingFunc=memoize(buildFormattingFunc);}
NativeFormatter.prototype.format=function(date,context){return this.buildFormattingFunc(this.standardDateProps,this.extendedSettings,context)(date);};NativeFormatter.prototype.formatRange=function(start,end,context){var _a=this,standardDateProps=_a.standardDateProps,extendedSettings=_a.extendedSettings;var diffSeverity=computeMarkerDiffSeverity(start.marker,end.marker,context.calendarSystem);if(!diffSeverity){return this.format(start,context);}
var biggestUnitForPartial=diffSeverity;if(biggestUnitForPartial>1&&(standardDateProps.year==='numeric'||standardDateProps.year==='2-digit')&&(standardDateProps.month==='numeric'||standardDateProps.month==='2-digit')&&(standardDateProps.day==='numeric'||standardDateProps.day==='2-digit')){biggestUnitForPartial=1;}
var full0=this.format(start,context);var full1=this.format(end,context);if(full0===full1){return full0;}
var partialDateProps=computePartialFormattingOptions(standardDateProps,biggestUnitForPartial);var partialFormattingFunc=buildFormattingFunc(partialDateProps,extendedSettings,context);var partial0=partialFormattingFunc(start);var partial1=partialFormattingFunc(end);var insertion=findCommonInsertion(full0,partial0,full1,partial1);var separator=extendedSettings.separator||'';if(insertion){return insertion.before+partial0+separator+partial1+insertion.after;}
return full0+separator+full1;};NativeFormatter.prototype.getLargestUnit=function(){switch(this.severity){case 7:case 6:case 5:return 'year';case 4:return 'month';case 3:return 'week';default:return 'day';}};return NativeFormatter;}());function buildFormattingFunc(standardDateProps,extendedSettings,context){var standardDatePropCnt=Object.keys(standardDateProps).length;if(standardDatePropCnt===1&&standardDateProps.timeZoneName==='short'){return function(date){return formatTimeZoneOffset(date.timeZoneOffset);};}
if(standardDatePropCnt===0&&extendedSettings.week){return function(date){return formatWeekNumber(context.computeWeekNumber(date.marker),context.weekLabel,context.locale,extendedSettings.week);};}
return buildNativeFormattingFunc(standardDateProps,extendedSettings,context);}
function buildNativeFormattingFunc(standardDateProps,extendedSettings,context){standardDateProps=__assign({},standardDateProps);extendedSettings=__assign({},extendedSettings);sanitizeSettings(standardDateProps,extendedSettings);standardDateProps.timeZone='UTC';var normalFormat=new Intl.DateTimeFormat(context.locale.codes,standardDateProps);var zeroFormat;if(extendedSettings.omitZeroMinute){var zeroProps=__assign({},standardDateProps);delete zeroProps.minute;zeroFormat=new Intl.DateTimeFormat(context.locale.codes,zeroProps);}
return function(date){var marker=date.marker;var format;if(zeroFormat&&!marker.getUTCMinutes()){format=zeroFormat;}
else{format=normalFormat;}
var s=format.format(marker);return postProcess(s,date,standardDateProps,extendedSettings,context);};}
function sanitizeSettings(standardDateProps,extendedSettings){if(standardDateProps.timeZoneName){if(!standardDateProps.hour){standardDateProps.hour='2-digit';}
if(!standardDateProps.minute){standardDateProps.minute='2-digit';}}
if(standardDateProps.timeZoneName==='long'){standardDateProps.timeZoneName='short';}
if(extendedSettings.omitZeroMinute&&(standardDateProps.second||standardDateProps.millisecond)){delete extendedSettings.omitZeroMinute;}}
function postProcess(s,date,standardDateProps,extendedSettings,context){s=s.replace(LTR_RE,'');if(standardDateProps.timeZoneName==='short'){s=injectTzoStr(s,(context.timeZone==='UTC'||date.timeZoneOffset==null)?'UTC':formatTimeZoneOffset(date.timeZoneOffset));}
if(extendedSettings.omitCommas){s=s.replace(COMMA_RE,'').trim();}
if(extendedSettings.omitZeroMinute){s=s.replace(':00','');}
if(extendedSettings.meridiem===false){s=s.replace(MERIDIEM_RE,'').trim();}
else if(extendedSettings.meridiem==='narrow'){s=s.replace(MERIDIEM_RE,function(m0,m1){return m1.toLocaleLowerCase();});}
else if(extendedSettings.meridiem==='short'){s=s.replace(MERIDIEM_RE,function(m0,m1){return m1.toLocaleLowerCase()+'m';});}
else if(extendedSettings.meridiem==='lowercase'){s=s.replace(MERIDIEM_RE,function(m0){return m0.toLocaleLowerCase();});}
s=s.replace(MULTI_SPACE_RE,' ');s=s.trim();return s;}
function injectTzoStr(s,tzoStr){var replaced=false;s=s.replace(UTC_RE,function(){replaced=true;return tzoStr;});if(!replaced){s+=' '+tzoStr;}
return s;}
function formatWeekNumber(num,weekLabel,locale,display){var parts=[];if(display==='narrow'){parts.push(weekLabel);}
else if(display==='short'){parts.push(weekLabel,' ');}
parts.push(locale.simpleNumberFormat.format(num));if(locale.options.isRtl){parts.reverse();}
return parts.join('');}
function computeMarkerDiffSeverity(d0,d1,ca){if(ca.getMarkerYear(d0)!==ca.getMarkerYear(d1)){return 5;}
if(ca.getMarkerMonth(d0)!==ca.getMarkerMonth(d1)){return 4;}
if(ca.getMarkerDay(d0)!==ca.getMarkerDay(d1)){return 2;}
if(timeAsMs(d0)!==timeAsMs(d1)){return 1;}
return 0;}
function computePartialFormattingOptions(options,biggestUnit){var partialOptions={};for(var name_2 in options){if(!(name_2 in STANDARD_DATE_PROP_SEVERITIES)||STANDARD_DATE_PROP_SEVERITIES[name_2]<=biggestUnit){partialOptions[name_2]=options[name_2];}}
return partialOptions;}
function findCommonInsertion(full0,partial0,full1,partial1){var i0=0;while(i0<full0.length){var found0=full0.indexOf(partial0,i0);if(found0===-1){break;}
var before0=full0.substr(0,found0);i0=found0+partial0.length;var after0=full0.substr(i0);var i1=0;while(i1<full1.length){var found1=full1.indexOf(partial1,i1);if(found1===-1){break;}
var before1=full1.substr(0,found1);i1=found1+partial1.length;var after1=full1.substr(i1);if(before0===before1&&after0===after1){return{before:before0,after:after0};}}}
return null;}
var CmdFormatter=(function(){function CmdFormatter(cmdStr,separator){this.cmdStr=cmdStr;this.separator=separator;}
CmdFormatter.prototype.format=function(date,context){return context.cmdFormatter(this.cmdStr,createVerboseFormattingArg(date,null,context,this.separator));};CmdFormatter.prototype.formatRange=function(start,end,context){return context.cmdFormatter(this.cmdStr,createVerboseFormattingArg(start,end,context,this.separator));};return CmdFormatter;}());var FuncFormatter=(function(){function FuncFormatter(func){this.func=func;}
FuncFormatter.prototype.format=function(date,context){return this.func(createVerboseFormattingArg(date,null,context));};FuncFormatter.prototype.formatRange=function(start,end,context){return this.func(createVerboseFormattingArg(start,end,context));};return FuncFormatter;}());function createFormatter(input,defaultSeparator){if(typeof input==='object'&&input){if(typeof defaultSeparator==='string'){input=__assign({separator:defaultSeparator},input);}
return new NativeFormatter(input);}
else if(typeof input==='string'){return new CmdFormatter(input,defaultSeparator);}
else if(typeof input==='function'){return new FuncFormatter(input);}}
function buildIsoString(marker,timeZoneOffset,stripZeroTime){if(stripZeroTime===void 0){stripZeroTime=false;}
var s=marker.toISOString();s=s.replace('.000','');if(stripZeroTime){s=s.replace('T00:00:00Z','');}
if(s.length>10){if(timeZoneOffset==null){s=s.replace('Z','');}
else if(timeZoneOffset!==0){s=s.replace('Z',formatTimeZoneOffset(timeZoneOffset,true));}}
return s;}
function formatIsoTimeString(marker){return padStart(marker.getUTCHours(),2)+':'+
padStart(marker.getUTCMinutes(),2)+':'+
padStart(marker.getUTCSeconds(),2);}
function formatTimeZoneOffset(minutes,doIso){if(doIso===void 0){doIso=false;}
var sign=minutes<0?'-':'+';var abs=Math.abs(minutes);var hours=Math.floor(abs/60);var mins=Math.round(abs%60);if(doIso){return sign+padSta;
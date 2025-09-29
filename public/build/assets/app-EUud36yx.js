var Ld=Object.defineProperty;var Od=(e,t,n)=>t in e?Ld(e,t,{enumerable:!0,configurable:!0,writable:!0,value:n}):e[t]=n;var Ji=(e,t,n)=>Od(e,typeof t!="symbol"?t+"":t,n);var qa={exports:{}},Pi={},Ja={exports:{}},F={};/**
 * @license React
 * react.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var hr=Symbol.for("react.element"),Fd=Symbol.for("react.portal"),Ad=Symbol.for("react.fragment"),Md=Symbol.for("react.strict_mode"),Dd=Symbol.for("react.profiler"),Ud=Symbol.for("react.provider"),Id=Symbol.for("react.context"),$d=Symbol.for("react.forward_ref"),Bd=Symbol.for("react.suspense"),Hd=Symbol.for("react.memo"),Vd=Symbol.for("react.lazy"),jl=Symbol.iterator;function Wd(e){return e===null||typeof e!="object"?null:(e=jl&&e[jl]||e["@@iterator"],typeof e=="function"?e:null)}var Ga={isMounted:function(){return!1},enqueueForceUpdate:function(){},enqueueReplaceState:function(){},enqueueSetState:function(){}},Za=Object.assign,eu={};function bn(e,t,n){this.props=e,this.context=t,this.refs=eu,this.updater=n||Ga}bn.prototype.isReactComponent={};bn.prototype.setState=function(e,t){if(typeof e!="object"&&typeof e!="function"&&e!=null)throw Error("setState(...): takes an object of state variables to update or a function which returns an object of state variables.");this.updater.enqueueSetState(this,e,t,"setState")};bn.prototype.forceUpdate=function(e){this.updater.enqueueForceUpdate(this,e,"forceUpdate")};function tu(){}tu.prototype=bn.prototype;function bs(e,t,n){this.props=e,this.context=t,this.refs=eu,this.updater=n||Ga}var Es=bs.prototype=new tu;Es.constructor=bs;Za(Es,bn.prototype);Es.isPureReactComponent=!0;var bl=Array.isArray,nu=Object.prototype.hasOwnProperty,Cs={current:null},ru={key:!0,ref:!0,__self:!0,__source:!0};function iu(e,t,n){var r,i={},o=null,s=null;if(t!=null)for(r in t.ref!==void 0&&(s=t.ref),t.key!==void 0&&(o=""+t.key),t)nu.call(t,r)&&!ru.hasOwnProperty(r)&&(i[r]=t[r]);var l=arguments.length-2;if(l===1)i.children=n;else if(1<l){for(var u=Array(l),c=0;c<l;c++)u[c]=arguments[c+2];i.children=u}if(e&&e.defaultProps)for(r in l=e.defaultProps,l)i[r]===void 0&&(i[r]=l[r]);return{$$typeof:hr,type:e,key:o,ref:s,props:i,_owner:Cs.current}}function Qd(e,t){return{$$typeof:hr,type:e.type,key:t,ref:e.ref,props:e.props,_owner:e._owner}}function _s(e){return typeof e=="object"&&e!==null&&e.$$typeof===hr}function Kd(e){var t={"=":"=0",":":"=2"};return"$"+e.replace(/[=:]/g,function(n){return t[n]})}var El=/\/+/g;function Gi(e,t){return typeof e=="object"&&e!==null&&e.key!=null?Kd(""+e.key):t.toString(36)}function Vr(e,t,n,r,i){var o=typeof e;(o==="undefined"||o==="boolean")&&(e=null);var s=!1;if(e===null)s=!0;else switch(o){case"string":case"number":s=!0;break;case"object":switch(e.$$typeof){case hr:case Fd:s=!0}}if(s)return s=e,i=i(s),e=r===""?"."+Gi(s,0):r,bl(i)?(n="",e!=null&&(n=e.replace(El,"$&/")+"/"),Vr(i,t,n,"",function(c){return c})):i!=null&&(_s(i)&&(i=Qd(i,n+(!i.key||s&&s.key===i.key?"":(""+i.key).replace(El,"$&/")+"/")+e)),t.push(i)),1;if(s=0,r=r===""?".":r+":",bl(e))for(var l=0;l<e.length;l++){o=e[l];var u=r+Gi(o,l);s+=Vr(o,t,n,u,i)}else if(u=Wd(e),typeof u=="function")for(e=u.call(e),l=0;!(o=e.next()).done;)o=o.value,u=r+Gi(o,l++),s+=Vr(o,t,n,u,i);else if(o==="object")throw t=String(e),Error("Objects are not valid as a React child (found: "+(t==="[object Object]"?"object with keys {"+Object.keys(e).join(", ")+"}":t)+"). If you meant to render a collection of children, use an array instead.");return s}function Er(e,t,n){if(e==null)return e;var r=[],i=0;return Vr(e,r,"","",function(o){return t.call(n,o,i++)}),r}function Yd(e){if(e._status===-1){var t=e._result;t=t(),t.then(function(n){(e._status===0||e._status===-1)&&(e._status=1,e._result=n)},function(n){(e._status===0||e._status===-1)&&(e._status=2,e._result=n)}),e._status===-1&&(e._status=0,e._result=t)}if(e._status===1)return e._result.default;throw e._result}var fe={current:null},Wr={transition:null},Xd={ReactCurrentDispatcher:fe,ReactCurrentBatchConfig:Wr,ReactCurrentOwner:Cs};function ou(){throw Error("act(...) is not supported in production builds of React.")}F.Children={map:Er,forEach:function(e,t,n){Er(e,function(){t.apply(this,arguments)},n)},count:function(e){var t=0;return Er(e,function(){t++}),t},toArray:function(e){return Er(e,function(t){return t})||[]},only:function(e){if(!_s(e))throw Error("React.Children.only expected to receive a single React element child.");return e}};F.Component=bn;F.Fragment=Ad;F.Profiler=Dd;F.PureComponent=bs;F.StrictMode=Md;F.Suspense=Bd;F.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED=Xd;F.act=ou;F.cloneElement=function(e,t,n){if(e==null)throw Error("React.cloneElement(...): The argument must be a React element, but you passed "+e+".");var r=Za({},e.props),i=e.key,o=e.ref,s=e._owner;if(t!=null){if(t.ref!==void 0&&(o=t.ref,s=Cs.current),t.key!==void 0&&(i=""+t.key),e.type&&e.type.defaultProps)var l=e.type.defaultProps;for(u in t)nu.call(t,u)&&!ru.hasOwnProperty(u)&&(r[u]=t[u]===void 0&&l!==void 0?l[u]:t[u])}var u=arguments.length-2;if(u===1)r.children=n;else if(1<u){l=Array(u);for(var c=0;c<u;c++)l[c]=arguments[c+2];r.children=l}return{$$typeof:hr,type:e.type,key:i,ref:o,props:r,_owner:s}};F.createContext=function(e){return e={$$typeof:Id,_currentValue:e,_currentValue2:e,_threadCount:0,Provider:null,Consumer:null,_defaultValue:null,_globalName:null},e.Provider={$$typeof:Ud,_context:e},e.Consumer=e};F.createElement=iu;F.createFactory=function(e){var t=iu.bind(null,e);return t.type=e,t};F.createRef=function(){return{current:null}};F.forwardRef=function(e){return{$$typeof:$d,render:e}};F.isValidElement=_s;F.lazy=function(e){return{$$typeof:Vd,_payload:{_status:-1,_result:e},_init:Yd}};F.memo=function(e,t){return{$$typeof:Hd,type:e,compare:t===void 0?null:t}};F.startTransition=function(e){var t=Wr.transition;Wr.transition={};try{e()}finally{Wr.transition=t}};F.unstable_act=ou;F.useCallback=function(e,t){return fe.current.useCallback(e,t)};F.useContext=function(e){return fe.current.useContext(e)};F.useDebugValue=function(){};F.useDeferredValue=function(e){return fe.current.useDeferredValue(e)};F.useEffect=function(e,t){return fe.current.useEffect(e,t)};F.useId=function(){return fe.current.useId()};F.useImperativeHandle=function(e,t,n){return fe.current.useImperativeHandle(e,t,n)};F.useInsertionEffect=function(e,t){return fe.current.useInsertionEffect(e,t)};F.useLayoutEffect=function(e,t){return fe.current.useLayoutEffect(e,t)};F.useMemo=function(e,t){return fe.current.useMemo(e,t)};F.useReducer=function(e,t,n){return fe.current.useReducer(e,t,n)};F.useRef=function(e){return fe.current.useRef(e)};F.useState=function(e){return fe.current.useState(e)};F.useSyncExternalStore=function(e,t,n){return fe.current.useSyncExternalStore(e,t,n)};F.useTransition=function(){return fe.current.useTransition()};F.version="18.3.1";Ja.exports=F;var O=Ja.exports;/**
 * @license React
 * react-jsx-runtime.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var qd=O,Jd=Symbol.for("react.element"),Gd=Symbol.for("react.fragment"),Zd=Object.prototype.hasOwnProperty,ef=qd.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,tf={key:!0,ref:!0,__self:!0,__source:!0};function su(e,t,n){var r,i={},o=null,s=null;n!==void 0&&(o=""+n),t.key!==void 0&&(o=""+t.key),t.ref!==void 0&&(s=t.ref);for(r in t)Zd.call(t,r)&&!tf.hasOwnProperty(r)&&(i[r]=t[r]);if(e&&e.defaultProps)for(r in t=e.defaultProps,t)i[r]===void 0&&(i[r]=t[r]);return{$$typeof:Jd,type:e,key:o,ref:s,props:i,_owner:ef.current}}Pi.Fragment=Gd;Pi.jsx=su;Pi.jsxs=su;qa.exports=Pi;var a=qa.exports,lu={exports:{}},Ce={},au={exports:{}},uu={};/**
 * @license React
 * scheduler.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */(function(e){function t(b,P){var R=b.length;b.push(P);e:for(;0<R;){var M=R-1>>>1,B=b[M];if(0<i(B,P))b[M]=P,b[R]=B,R=M;else break e}}function n(b){return b.length===0?null:b[0]}function r(b){if(b.length===0)return null;var P=b[0],R=b.pop();if(R!==P){b[0]=R;e:for(var M=0,B=b.length,ct=B>>>1;M<ct;){var ze=2*(M+1)-1,qt=b[ze],Tt=ze+1,br=b[Tt];if(0>i(qt,R))Tt<B&&0>i(br,qt)?(b[M]=br,b[Tt]=R,M=Tt):(b[M]=qt,b[ze]=R,M=ze);else if(Tt<B&&0>i(br,R))b[M]=br,b[Tt]=R,M=Tt;else break e}}return P}function i(b,P){var R=b.sortIndex-P.sortIndex;return R!==0?R:b.id-P.id}if(typeof performance=="object"&&typeof performance.now=="function"){var o=performance;e.unstable_now=function(){return o.now()}}else{var s=Date,l=s.now();e.unstable_now=function(){return s.now()-l}}var u=[],c=[],p=1,g=null,y=3,S=!1,m=!1,v=!1,w=typeof setTimeout=="function"?setTimeout:null,d=typeof clearTimeout=="function"?clearTimeout:null,f=typeof setImmediate<"u"?setImmediate:null;typeof navigator<"u"&&navigator.scheduling!==void 0&&navigator.scheduling.isInputPending!==void 0&&navigator.scheduling.isInputPending.bind(navigator.scheduling);function h(b){for(var P=n(c);P!==null;){if(P.callback===null)r(c);else if(P.startTime<=b)r(c),P.sortIndex=P.expirationTime,t(u,P);else break;P=n(c)}}function k(b){if(v=!1,h(b),!m)if(n(u)!==null)m=!0,he(j);else{var P=n(c);P!==null&&Xt(k,P.startTime-b)}}function j(b,P){m=!1,v&&(v=!1,d(z),z=-1),S=!0;var R=y;try{for(h(P),g=n(u);g!==null&&(!(g.expirationTime>P)||b&&!me());){var M=g.callback;if(typeof M=="function"){g.callback=null,y=g.priorityLevel;var B=M(g.expirationTime<=P);P=e.unstable_now(),typeof B=="function"?g.callback=B:g===n(u)&&r(u),h(P)}else r(u);g=n(u)}if(g!==null)var ct=!0;else{var ze=n(c);ze!==null&&Xt(k,ze.startTime-P),ct=!1}return ct}finally{g=null,y=R,S=!1}}var _=!1,E=null,z=-1,I=5,L=-1;function me(){return!(e.unstable_now()-L<I)}function Qe(){if(E!==null){var b=e.unstable_now();L=b;var P=!0;try{P=E(!0,b)}finally{P?Me():(_=!1,E=null)}}else _=!1}var Me;if(typeof f=="function")Me=function(){f(Qe)};else if(typeof MessageChannel<"u"){var Ke=new MessageChannel,jr=Ke.port2;Ke.port1.onmessage=Qe,Me=function(){jr.postMessage(null)}}else Me=function(){w(Qe,0)};function he(b){E=b,_||(_=!0,Me())}function Xt(b,P){z=w(function(){b(e.unstable_now())},P)}e.unstable_IdlePriority=5,e.unstable_ImmediatePriority=1,e.unstable_LowPriority=4,e.unstable_NormalPriority=3,e.unstable_Profiling=null,e.unstable_UserBlockingPriority=2,e.unstable_cancelCallback=function(b){b.callback=null},e.unstable_continueExecution=function(){m||S||(m=!0,he(j))},e.unstable_forceFrameRate=function(b){0>b||125<b?console.error("forceFrameRate takes a positive int between 0 and 125, forcing frame rates higher than 125 fps is not supported"):I=0<b?Math.floor(1e3/b):5},e.unstable_getCurrentPriorityLevel=function(){return y},e.unstable_getFirstCallbackNode=function(){return n(u)},e.unstable_next=function(b){switch(y){case 1:case 2:case 3:var P=3;break;default:P=y}var R=y;y=P;try{return b()}finally{y=R}},e.unstable_pauseExecution=function(){},e.unstable_requestPaint=function(){},e.unstable_runWithPriority=function(b,P){switch(b){case 1:case 2:case 3:case 4:case 5:break;default:b=3}var R=y;y=b;try{return P()}finally{y=R}},e.unstable_scheduleCallback=function(b,P,R){var M=e.unstable_now();switch(typeof R=="object"&&R!==null?(R=R.delay,R=typeof R=="number"&&0<R?M+R:M):R=M,b){case 1:var B=-1;break;case 2:B=250;break;case 5:B=1073741823;break;case 4:B=1e4;break;default:B=5e3}return B=R+B,b={id:p++,callback:P,priorityLevel:b,startTime:R,expirationTime:B,sortIndex:-1},R>M?(b.sortIndex=R,t(c,b),n(u)===null&&b===n(c)&&(v?(d(z),z=-1):v=!0,Xt(k,R-M))):(b.sortIndex=B,t(u,b),m||S||(m=!0,he(j))),b},e.unstable_shouldYield=me,e.unstable_wrapCallback=function(b){var P=y;return function(){var R=y;y=P;try{return b.apply(this,arguments)}finally{y=R}}}})(uu);au.exports=uu;var nf=au.exports;/**
 * @license React
 * react-dom.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var rf=O,Ee=nf;function N(e){for(var t="https://reactjs.org/docs/error-decoder.html?invariant="+e,n=1;n<arguments.length;n++)t+="&args[]="+encodeURIComponent(arguments[n]);return"Minified React error #"+e+"; visit "+t+" for the full message or use the non-minified dev environment for full errors and additional helpful warnings."}var cu=new Set,Gn={};function Kt(e,t){yn(e,t),yn(e+"Capture",t)}function yn(e,t){for(Gn[e]=t,e=0;e<t.length;e++)cu.add(t[e])}var ot=!(typeof window>"u"||typeof window.document>"u"||typeof window.document.createElement>"u"),_o=Object.prototype.hasOwnProperty,of=/^[:A-Z_a-z\u00C0-\u00D6\u00D8-\u00F6\u00F8-\u02FF\u0370-\u037D\u037F-\u1FFF\u200C-\u200D\u2070-\u218F\u2C00-\u2FEF\u3001-\uD7FF\uF900-\uFDCF\uFDF0-\uFFFD][:A-Z_a-z\u00C0-\u00D6\u00D8-\u00F6\u00F8-\u02FF\u0370-\u037D\u037F-\u1FFF\u200C-\u200D\u2070-\u218F\u2C00-\u2FEF\u3001-\uD7FF\uF900-\uFDCF\uFDF0-\uFFFD\-.0-9\u00B7\u0300-\u036F\u203F-\u2040]*$/,Cl={},_l={};function sf(e){return _o.call(_l,e)?!0:_o.call(Cl,e)?!1:of.test(e)?_l[e]=!0:(Cl[e]=!0,!1)}function lf(e,t,n,r){if(n!==null&&n.type===0)return!1;switch(typeof t){case"function":case"symbol":return!0;case"boolean":return r?!1:n!==null?!n.acceptsBooleans:(e=e.toLowerCase().slice(0,5),e!=="data-"&&e!=="aria-");default:return!1}}function af(e,t,n,r){if(t===null||typeof t>"u"||lf(e,t,n,r))return!0;if(r)return!1;if(n!==null)switch(n.type){case 3:return!t;case 4:return t===!1;case 5:return isNaN(t);case 6:return isNaN(t)||1>t}return!1}function pe(e,t,n,r,i,o,s){this.acceptsBooleans=t===2||t===3||t===4,this.attributeName=r,this.attributeNamespace=i,this.mustUseProperty=n,this.propertyName=e,this.type=t,this.sanitizeURL=o,this.removeEmptyString=s}var ie={};"children dangerouslySetInnerHTML defaultValue defaultChecked innerHTML suppressContentEditableWarning suppressHydrationWarning style".split(" ").forEach(function(e){ie[e]=new pe(e,0,!1,e,null,!1,!1)});[["acceptCharset","accept-charset"],["className","class"],["htmlFor","for"],["httpEquiv","http-equiv"]].forEach(function(e){var t=e[0];ie[t]=new pe(t,1,!1,e[1],null,!1,!1)});["contentEditable","draggable","spellCheck","value"].forEach(function(e){ie[e]=new pe(e,2,!1,e.toLowerCase(),null,!1,!1)});["autoReverse","externalResourcesRequired","focusable","preserveAlpha"].forEach(function(e){ie[e]=new pe(e,2,!1,e,null,!1,!1)});"allowFullScreen async autoFocus autoPlay controls default defer disabled disablePictureInPicture disableRemotePlayback formNoValidate hidden loop noModule noValidate open playsInline readOnly required reversed scoped seamless itemScope".split(" ").forEach(function(e){ie[e]=new pe(e,3,!1,e.toLowerCase(),null,!1,!1)});["checked","multiple","muted","selected"].forEach(function(e){ie[e]=new pe(e,3,!0,e,null,!1,!1)});["capture","download"].forEach(function(e){ie[e]=new pe(e,4,!1,e,null,!1,!1)});["cols","rows","size","span"].forEach(function(e){ie[e]=new pe(e,6,!1,e,null,!1,!1)});["rowSpan","start"].forEach(function(e){ie[e]=new pe(e,5,!1,e.toLowerCase(),null,!1,!1)});var zs=/[\-:]([a-z])/g;function Ps(e){return e[1].toUpperCase()}"accent-height alignment-baseline arabic-form baseline-shift cap-height clip-path clip-rule color-interpolation color-interpolation-filters color-profile color-rendering dominant-baseline enable-background fill-opacity fill-rule flood-color flood-opacity font-family font-size font-size-adjust font-stretch font-style font-variant font-weight glyph-name glyph-orientation-horizontal glyph-orientation-vertical horiz-adv-x horiz-origin-x image-rendering letter-spacing lighting-color marker-end marker-mid marker-start overline-position overline-thickness paint-order panose-1 pointer-events rendering-intent shape-rendering stop-color stop-opacity strikethrough-position strikethrough-thickness stroke-dasharray stroke-dashoffset stroke-linecap stroke-linejoin stroke-miterlimit stroke-opacity stroke-width text-anchor text-decoration text-rendering underline-position underline-thickness unicode-bidi unicode-range units-per-em v-alphabetic v-hanging v-ideographic v-mathematical vector-effect vert-adv-y vert-origin-x vert-origin-y word-spacing writing-mode xmlns:xlink x-height".split(" ").forEach(function(e){var t=e.replace(zs,Ps);ie[t]=new pe(t,1,!1,e,null,!1,!1)});"xlink:actuate xlink:arcrole xlink:role xlink:show xlink:title xlink:type".split(" ").forEach(function(e){var t=e.replace(zs,Ps);ie[t]=new pe(t,1,!1,e,"http://www.w3.org/1999/xlink",!1,!1)});["xml:base","xml:lang","xml:space"].forEach(function(e){var t=e.replace(zs,Ps);ie[t]=new pe(t,1,!1,e,"http://www.w3.org/XML/1998/namespace",!1,!1)});["tabIndex","crossOrigin"].forEach(function(e){ie[e]=new pe(e,1,!1,e.toLowerCase(),null,!1,!1)});ie.xlinkHref=new pe("xlinkHref",1,!1,"xlink:href","http://www.w3.org/1999/xlink",!0,!1);["src","href","action","formAction"].forEach(function(e){ie[e]=new pe(e,1,!1,e.toLowerCase(),null,!0,!0)});function Ts(e,t,n,r){var i=ie.hasOwnProperty(t)?ie[t]:null;(i!==null?i.type!==0:r||!(2<t.length)||t[0]!=="o"&&t[0]!=="O"||t[1]!=="n"&&t[1]!=="N")&&(af(t,n,i,r)&&(n=null),r||i===null?sf(t)&&(n===null?e.removeAttribute(t):e.setAttribute(t,""+n)):i.mustUseProperty?e[i.propertyName]=n===null?i.type===3?!1:"":n:(t=i.attributeName,r=i.attributeNamespace,n===null?e.removeAttribute(t):(i=i.type,n=i===3||i===4&&n===!0?"":""+n,r?e.setAttributeNS(r,t,n):e.setAttribute(t,n))))}var ut=rf.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED,Cr=Symbol.for("react.element"),Gt=Symbol.for("react.portal"),Zt=Symbol.for("react.fragment"),Rs=Symbol.for("react.strict_mode"),zo=Symbol.for("react.profiler"),du=Symbol.for("react.provider"),fu=Symbol.for("react.context"),Ls=Symbol.for("react.forward_ref"),Po=Symbol.for("react.suspense"),To=Symbol.for("react.suspense_list"),Os=Symbol.for("react.memo"),ft=Symbol.for("react.lazy"),pu=Symbol.for("react.offscreen"),zl=Symbol.iterator;function Pn(e){return e===null||typeof e!="object"?null:(e=zl&&e[zl]||e["@@iterator"],typeof e=="function"?e:null)}var Y=Object.assign,Zi;function Un(e){if(Zi===void 0)try{throw Error()}catch(n){var t=n.stack.trim().match(/\n( *(at )?)/);Zi=t&&t[1]||""}return`
`+Zi+e}var eo=!1;function to(e,t){if(!e||eo)return"";eo=!0;var n=Error.prepareStackTrace;Error.prepareStackTrace=void 0;try{if(t)if(t=function(){throw Error()},Object.defineProperty(t.prototype,"props",{set:function(){throw Error()}}),typeof Reflect=="object"&&Reflect.construct){try{Reflect.construct(t,[])}catch(c){var r=c}Reflect.construct(e,[],t)}else{try{t.call()}catch(c){r=c}e.call(t.prototype)}else{try{throw Error()}catch(c){r=c}e()}}catch(c){if(c&&r&&typeof c.stack=="string"){for(var i=c.stack.split(`
`),o=r.stack.split(`
`),s=i.length-1,l=o.length-1;1<=s&&0<=l&&i[s]!==o[l];)l--;for(;1<=s&&0<=l;s--,l--)if(i[s]!==o[l]){if(s!==1||l!==1)do if(s--,l--,0>l||i[s]!==o[l]){var u=`
`+i[s].replace(" at new "," at ");return e.displayName&&u.includes("<anonymous>")&&(u=u.replace("<anonymous>",e.displayName)),u}while(1<=s&&0<=l);break}}}finally{eo=!1,Error.prepareStackTrace=n}return(e=e?e.displayName||e.name:"")?Un(e):""}function uf(e){switch(e.tag){case 5:return Un(e.type);case 16:return Un("Lazy");case 13:return Un("Suspense");case 19:return Un("SuspenseList");case 0:case 2:case 15:return e=to(e.type,!1),e;case 11:return e=to(e.type.render,!1),e;case 1:return e=to(e.type,!0),e;default:return""}}function Ro(e){if(e==null)return null;if(typeof e=="function")return e.displayName||e.name||null;if(typeof e=="string")return e;switch(e){case Zt:return"Fragment";case Gt:return"Portal";case zo:return"Profiler";case Rs:return"StrictMode";case Po:return"Suspense";case To:return"SuspenseList"}if(typeof e=="object")switch(e.$$typeof){case fu:return(e.displayName||"Context")+".Consumer";case du:return(e._context.displayName||"Context")+".Provider";case Ls:var t=e.render;return e=e.displayName,e||(e=t.displayName||t.name||"",e=e!==""?"ForwardRef("+e+")":"ForwardRef"),e;case Os:return t=e.displayName||null,t!==null?t:Ro(e.type)||"Memo";case ft:t=e._payload,e=e._init;try{return Ro(e(t))}catch{}}return null}function cf(e){var t=e.type;switch(e.tag){case 24:return"Cache";case 9:return(t.displayName||"Context")+".Consumer";case 10:return(t._context.displayName||"Context")+".Provider";case 18:return"DehydratedFragment";case 11:return e=t.render,e=e.displayName||e.name||"",t.displayName||(e!==""?"ForwardRef("+e+")":"ForwardRef");case 7:return"Fragment";case 5:return t;case 4:return"Portal";case 3:return"Root";case 6:return"Text";case 16:return Ro(t);case 8:return t===Rs?"StrictMode":"Mode";case 22:return"Offscreen";case 12:return"Profiler";case 21:return"Scope";case 13:return"Suspense";case 19:return"SuspenseList";case 25:return"TracingMarker";case 1:case 0:case 17:case 2:case 14:case 15:if(typeof t=="function")return t.displayName||t.name||null;if(typeof t=="string")return t}return null}function Et(e){switch(typeof e){case"boolean":case"number":case"string":case"undefined":return e;case"object":return e;default:return""}}function mu(e){var t=e.type;return(e=e.nodeName)&&e.toLowerCase()==="input"&&(t==="checkbox"||t==="radio")}function df(e){var t=mu(e)?"checked":"value",n=Object.getOwnPropertyDescriptor(e.constructor.prototype,t),r=""+e[t];if(!e.hasOwnProperty(t)&&typeof n<"u"&&typeof n.get=="function"&&typeof n.set=="function"){var i=n.get,o=n.set;return Object.defineProperty(e,t,{configurable:!0,get:function(){return i.call(this)},set:function(s){r=""+s,o.call(this,s)}}),Object.defineProperty(e,t,{enumerable:n.enumerable}),{getValue:function(){return r},setValue:function(s){r=""+s},stopTracking:function(){e._valueTracker=null,delete e[t]}}}}function _r(e){e._valueTracker||(e._valueTracker=df(e))}function hu(e){if(!e)return!1;var t=e._valueTracker;if(!t)return!0;var n=t.getValue(),r="";return e&&(r=mu(e)?e.checked?"true":"false":e.value),e=r,e!==n?(t.setValue(e),!0):!1}function oi(e){if(e=e||(typeof document<"u"?document:void 0),typeof e>"u")return null;try{return e.activeElement||e.body}catch{return e.body}}function Lo(e,t){var n=t.checked;return Y({},t,{defaultChecked:void 0,defaultValue:void 0,value:void 0,checked:n??e._wrapperState.initialChecked})}function Pl(e,t){var n=t.defaultValue==null?"":t.defaultValue,r=t.checked!=null?t.checked:t.defaultChecked;n=Et(t.value!=null?t.value:n),e._wrapperState={initialChecked:r,initialValue:n,controlled:t.type==="checkbox"||t.type==="radio"?t.checked!=null:t.value!=null}}function gu(e,t){t=t.checked,t!=null&&Ts(e,"checked",t,!1)}function Oo(e,t){gu(e,t);var n=Et(t.value),r=t.type;if(n!=null)r==="number"?(n===0&&e.value===""||e.value!=n)&&(e.value=""+n):e.value!==""+n&&(e.value=""+n);else if(r==="submit"||r==="reset"){e.removeAttribute("value");return}t.hasOwnProperty("value")?Fo(e,t.type,n):t.hasOwnProperty("defaultValue")&&Fo(e,t.type,Et(t.defaultValue)),t.checked==null&&t.defaultChecked!=null&&(e.defaultChecked=!!t.defaultChecked)}function Tl(e,t,n){if(t.hasOwnProperty("value")||t.hasOwnProperty("defaultValue")){var r=t.type;if(!(r!=="submit"&&r!=="reset"||t.value!==void 0&&t.value!==null))return;t=""+e._wrapperState.initialValue,n||t===e.value||(e.value=t),e.defaultValue=t}n=e.name,n!==""&&(e.name=""),e.defaultChecked=!!e._wrapperState.initialChecked,n!==""&&(e.name=n)}function Fo(e,t,n){(t!=="number"||oi(e.ownerDocument)!==e)&&(n==null?e.defaultValue=""+e._wrapperState.initialValue:e.defaultValue!==""+n&&(e.defaultValue=""+n))}var In=Array.isArray;function dn(e,t,n,r){if(e=e.options,t){t={};for(var i=0;i<n.length;i++)t["$"+n[i]]=!0;for(n=0;n<e.length;n++)i=t.hasOwnProperty("$"+e[n].value),e[n].selected!==i&&(e[n].selected=i),i&&r&&(e[n].defaultSelected=!0)}else{for(n=""+Et(n),t=null,i=0;i<e.length;i++){if(e[i].value===n){e[i].selected=!0,r&&(e[i].defaultSelected=!0);return}t!==null||e[i].disabled||(t=e[i])}t!==null&&(t.selected=!0)}}function Ao(e,t){if(t.dangerouslySetInnerHTML!=null)throw Error(N(91));return Y({},t,{value:void 0,defaultValue:void 0,children:""+e._wrapperState.initialValue})}function Rl(e,t){var n=t.value;if(n==null){if(n=t.children,t=t.defaultValue,n!=null){if(t!=null)throw Error(N(92));if(In(n)){if(1<n.length)throw Error(N(93));n=n[0]}t=n}t==null&&(t=""),n=t}e._wrapperState={initialValue:Et(n)}}function yu(e,t){var n=Et(t.value),r=Et(t.defaultValue);n!=null&&(n=""+n,n!==e.value&&(e.value=n),t.defaultValue==null&&e.defaultValue!==n&&(e.defaultValue=n)),r!=null&&(e.defaultValue=""+r)}function Ll(e){var t=e.textContent;t===e._wrapperState.initialValue&&t!==""&&t!==null&&(e.value=t)}function vu(e){switch(e){case"svg":return"http://www.w3.org/2000/svg";case"math":return"http://www.w3.org/1998/Math/MathML";default:return"http://www.w3.org/1999/xhtml"}}function Mo(e,t){return e==null||e==="http://www.w3.org/1999/xhtml"?vu(t):e==="http://www.w3.org/2000/svg"&&t==="foreignObject"?"http://www.w3.org/1999/xhtml":e}var zr,xu=function(e){return typeof MSApp<"u"&&MSApp.execUnsafeLocalFunction?function(t,n,r,i){MSApp.execUnsafeLocalFunction(function(){return e(t,n,r,i)})}:e}(function(e,t){if(e.namespaceURI!=="http://www.w3.org/2000/svg"||"innerHTML"in e)e.innerHTML=t;else{for(zr=zr||document.createElement("div"),zr.innerHTML="<svg>"+t.valueOf().toString()+"</svg>",t=zr.firstChild;e.firstChild;)e.removeChild(e.firstChild);for(;t.firstChild;)e.appendChild(t.firstChild)}});function Zn(e,t){if(t){var n=e.firstChild;if(n&&n===e.lastChild&&n.nodeType===3){n.nodeValue=t;return}}e.textContent=t}var Hn={animationIterationCount:!0,aspectRatio:!0,borderImageOutset:!0,borderImageSlice:!0,borderImageWidth:!0,boxFlex:!0,boxFlexGroup:!0,boxOrdinalGroup:!0,columnCount:!0,columns:!0,flex:!0,flexGrow:!0,flexPositive:!0,flexShrink:!0,flexNegative:!0,flexOrder:!0,gridArea:!0,gridRow:!0,gridRowEnd:!0,gridRowSpan:!0,gridRowStart:!0,gridColumn:!0,gridColumnEnd:!0,gridColumnSpan:!0,gridColumnStart:!0,fontWeight:!0,lineClamp:!0,lineHeight:!0,opacity:!0,order:!0,orphans:!0,tabSize:!0,widows:!0,zIndex:!0,zoom:!0,fillOpacity:!0,floodOpacity:!0,stopOpacity:!0,strokeDasharray:!0,strokeDashoffset:!0,strokeMiterlimit:!0,strokeOpacity:!0,strokeWidth:!0},ff=["Webkit","ms","Moz","O"];Object.keys(Hn).forEach(function(e){ff.forEach(function(t){t=t+e.charAt(0).toUpperCase()+e.substring(1),Hn[t]=Hn[e]})});function wu(e,t,n){return t==null||typeof t=="boolean"||t===""?"":n||typeof t!="number"||t===0||Hn.hasOwnProperty(e)&&Hn[e]?(""+t).trim():t+"px"}function ku(e,t){e=e.style;for(var n in t)if(t.hasOwnProperty(n)){var r=n.indexOf("--")===0,i=wu(n,t[n],r);n==="float"&&(n="cssFloat"),r?e.setProperty(n,i):e[n]=i}}var pf=Y({menuitem:!0},{area:!0,base:!0,br:!0,col:!0,embed:!0,hr:!0,img:!0,input:!0,keygen:!0,link:!0,meta:!0,param:!0,source:!0,track:!0,wbr:!0});function Do(e,t){if(t){if(pf[e]&&(t.children!=null||t.dangerouslySetInnerHTML!=null))throw Error(N(137,e));if(t.dangerouslySetInnerHTML!=null){if(t.children!=null)throw Error(N(60));if(typeof t.dangerouslySetInnerHTML!="object"||!("__html"in t.dangerouslySetInnerHTML))throw Error(N(61))}if(t.style!=null&&typeof t.style!="object")throw Error(N(62))}}function Uo(e,t){if(e.indexOf("-")===-1)return typeof t.is=="string";switch(e){case"annotation-xml":case"color-profile":case"font-face":case"font-face-src":case"font-face-uri":case"font-face-format":case"font-face-name":case"missing-glyph":return!1;default:return!0}}var Io=null;function Fs(e){return e=e.target||e.srcElement||window,e.correspondingUseElement&&(e=e.correspondingUseElement),e.nodeType===3?e.parentNode:e}var $o=null,fn=null,pn=null;function Ol(e){if(e=vr(e)){if(typeof $o!="function")throw Error(N(280));var t=e.stateNode;t&&(t=Fi(t),$o(e.stateNode,e.type,t))}}function Su(e){fn?pn?pn.push(e):pn=[e]:fn=e}function Nu(){if(fn){var e=fn,t=pn;if(pn=fn=null,Ol(e),t)for(e=0;e<t.length;e++)Ol(t[e])}}function ju(e,t){return e(t)}function bu(){}var no=!1;function Eu(e,t,n){if(no)return e(t,n);no=!0;try{return ju(e,t,n)}finally{no=!1,(fn!==null||pn!==null)&&(bu(),Nu())}}function er(e,t){var n=e.stateNode;if(n===null)return null;var r=Fi(n);if(r===null)return null;n=r[t];e:switch(t){case"onClick":case"onClickCapture":case"onDoubleClick":case"onDoubleClickCapture":case"onMouseDown":case"onMouseDownCapture":case"onMouseMove":case"onMouseMoveCapture":case"onMouseUp":case"onMouseUpCapture":case"onMouseEnter":(r=!r.disabled)||(e=e.type,r=!(e==="button"||e==="input"||e==="select"||e==="textarea")),e=!r;break e;default:e=!1}if(e)return null;if(n&&typeof n!="function")throw Error(N(231,t,typeof n));return n}var Bo=!1;if(ot)try{var Tn={};Object.defineProperty(Tn,"passive",{get:function(){Bo=!0}}),window.addEventListener("test",Tn,Tn),window.removeEventListener("test",Tn,Tn)}catch{Bo=!1}function mf(e,t,n,r,i,o,s,l,u){var c=Array.prototype.slice.call(arguments,3);try{t.apply(n,c)}catch(p){this.onError(p)}}var Vn=!1,si=null,li=!1,Ho=null,hf={onError:function(e){Vn=!0,si=e}};function gf(e,t,n,r,i,o,s,l,u){Vn=!1,si=null,mf.apply(hf,arguments)}function yf(e,t,n,r,i,o,s,l,u){if(gf.apply(this,arguments),Vn){if(Vn){var c=si;Vn=!1,si=null}else throw Error(N(198));li||(li=!0,Ho=c)}}function Yt(e){var t=e,n=e;if(e.alternate)for(;t.return;)t=t.return;else{e=t;do t=e,t.flags&4098&&(n=t.return),e=t.return;while(e)}return t.tag===3?n:null}function Cu(e){if(e.tag===13){var t=e.memoizedState;if(t===null&&(e=e.alternate,e!==null&&(t=e.memoizedState)),t!==null)return t.dehydrated}return null}function Fl(e){if(Yt(e)!==e)throw Error(N(188))}function vf(e){var t=e.alternate;if(!t){if(t=Yt(e),t===null)throw Error(N(188));return t!==e?null:e}for(var n=e,r=t;;){var i=n.return;if(i===null)break;var o=i.alternate;if(o===null){if(r=i.return,r!==null){n=r;continue}break}if(i.child===o.child){for(o=i.child;o;){if(o===n)return Fl(i),e;if(o===r)return Fl(i),t;o=o.sibling}throw Error(N(188))}if(n.return!==r.return)n=i,r=o;else{for(var s=!1,l=i.child;l;){if(l===n){s=!0,n=i,r=o;break}if(l===r){s=!0,r=i,n=o;break}l=l.sibling}if(!s){for(l=o.child;l;){if(l===n){s=!0,n=o,r=i;break}if(l===r){s=!0,r=o,n=i;break}l=l.sibling}if(!s)throw Error(N(189))}}if(n.alternate!==r)throw Error(N(190))}if(n.tag!==3)throw Error(N(188));return n.stateNode.current===n?e:t}function _u(e){return e=vf(e),e!==null?zu(e):null}function zu(e){if(e.tag===5||e.tag===6)return e;for(e=e.child;e!==null;){var t=zu(e);if(t!==null)return t;e=e.sibling}return null}var Pu=Ee.unstable_scheduleCallback,Al=Ee.unstable_cancelCallback,xf=Ee.unstable_shouldYield,wf=Ee.unstable_requestPaint,q=Ee.unstable_now,kf=Ee.unstable_getCurrentPriorityLevel,As=Ee.unstable_ImmediatePriority,Tu=Ee.unstable_UserBlockingPriority,ai=Ee.unstable_NormalPriority,Sf=Ee.unstable_LowPriority,Ru=Ee.unstable_IdlePriority,Ti=null,Ge=null;function Nf(e){if(Ge&&typeof Ge.onCommitFiberRoot=="function")try{Ge.onCommitFiberRoot(Ti,e,void 0,(e.current.flags&128)===128)}catch{}}var Be=Math.clz32?Math.clz32:Ef,jf=Math.log,bf=Math.LN2;function Ef(e){return e>>>=0,e===0?32:31-(jf(e)/bf|0)|0}var Pr=64,Tr=4194304;function $n(e){switch(e&-e){case 1:return 1;case 2:return 2;case 4:return 4;case 8:return 8;case 16:return 16;case 32:return 32;case 64:case 128:case 256:case 512:case 1024:case 2048:case 4096:case 8192:case 16384:case 32768:case 65536:case 131072:case 262144:case 524288:case 1048576:case 2097152:return e&4194240;case 4194304:case 8388608:case 16777216:case 33554432:case 67108864:return e&130023424;case 134217728:return 134217728;case 268435456:return 268435456;case 536870912:return 536870912;case 1073741824:return 1073741824;default:return e}}function ui(e,t){var n=e.pendingLanes;if(n===0)return 0;var r=0,i=e.suspendedLanes,o=e.pingedLanes,s=n&268435455;if(s!==0){var l=s&~i;l!==0?r=$n(l):(o&=s,o!==0&&(r=$n(o)))}else s=n&~i,s!==0?r=$n(s):o!==0&&(r=$n(o));if(r===0)return 0;if(t!==0&&t!==r&&!(t&i)&&(i=r&-r,o=t&-t,i>=o||i===16&&(o&4194240)!==0))return t;if(r&4&&(r|=n&16),t=e.entangledLanes,t!==0)for(e=e.entanglements,t&=r;0<t;)n=31-Be(t),i=1<<n,r|=e[n],t&=~i;return r}function Cf(e,t){switch(e){case 1:case 2:case 4:return t+250;case 8:case 16:case 32:case 64:case 128:case 256:case 512:case 1024:case 2048:case 4096:case 8192:case 16384:case 32768:case 65536:case 131072:case 262144:case 524288:case 1048576:case 2097152:return t+5e3;case 4194304:case 8388608:case 16777216:case 33554432:case 67108864:return-1;case 134217728:case 268435456:case 536870912:case 1073741824:return-1;default:return-1}}function _f(e,t){for(var n=e.suspendedLanes,r=e.pingedLanes,i=e.expirationTimes,o=e.pendingLanes;0<o;){var s=31-Be(o),l=1<<s,u=i[s];u===-1?(!(l&n)||l&r)&&(i[s]=Cf(l,t)):u<=t&&(e.expiredLanes|=l),o&=~l}}function Vo(e){return e=e.pendingLanes&-1073741825,e!==0?e:e&1073741824?1073741824:0}function Lu(){var e=Pr;return Pr<<=1,!(Pr&4194240)&&(Pr=64),e}function ro(e){for(var t=[],n=0;31>n;n++)t.push(e);return t}function gr(e,t,n){e.pendingLanes|=t,t!==536870912&&(e.suspendedLanes=0,e.pingedLanes=0),e=e.eventTimes,t=31-Be(t),e[t]=n}function zf(e,t){var n=e.pendingLanes&~t;e.pendingLanes=t,e.suspendedLanes=0,e.pingedLanes=0,e.expiredLanes&=t,e.mutableReadLanes&=t,e.entangledLanes&=t,t=e.entanglements;var r=e.eventTimes;for(e=e.expirationTimes;0<n;){var i=31-Be(n),o=1<<i;t[i]=0,r[i]=-1,e[i]=-1,n&=~o}}function Ms(e,t){var n=e.entangledLanes|=t;for(e=e.entanglements;n;){var r=31-Be(n),i=1<<r;i&t|e[r]&t&&(e[r]|=t),n&=~i}}var D=0;function Ou(e){return e&=-e,1<e?4<e?e&268435455?16:536870912:4:1}var Fu,Ds,Au,Mu,Du,Wo=!1,Rr=[],vt=null,xt=null,wt=null,tr=new Map,nr=new Map,mt=[],Pf="mousedown mouseup touchcancel touchend touchstart auxclick dblclick pointercancel pointerdown pointerup dragend dragstart drop compositionend compositionstart keydown keypress keyup input textInput copy cut paste click change contextmenu reset submit".split(" ");function Ml(e,t){switch(e){case"focusin":case"focusout":vt=null;break;case"dragenter":case"dragleave":xt=null;break;case"mouseover":case"mouseout":wt=null;break;case"pointerover":case"pointerout":tr.delete(t.pointerId);break;case"gotpointercapture":case"lostpointercapture":nr.delete(t.pointerId)}}function Rn(e,t,n,r,i,o){return e===null||e.nativeEvent!==o?(e={blockedOn:t,domEventName:n,eventSystemFlags:r,nativeEvent:o,targetContainers:[i]},t!==null&&(t=vr(t),t!==null&&Ds(t)),e):(e.eventSystemFlags|=r,t=e.targetContainers,i!==null&&t.indexOf(i)===-1&&t.push(i),e)}function Tf(e,t,n,r,i){switch(t){case"focusin":return vt=Rn(vt,e,t,n,r,i),!0;case"dragenter":return xt=Rn(xt,e,t,n,r,i),!0;case"mouseover":return wt=Rn(wt,e,t,n,r,i),!0;case"pointerover":var o=i.pointerId;return tr.set(o,Rn(tr.get(o)||null,e,t,n,r,i)),!0;case"gotpointercapture":return o=i.pointerId,nr.set(o,Rn(nr.get(o)||null,e,t,n,r,i)),!0}return!1}function Uu(e){var t=Ot(e.target);if(t!==null){var n=Yt(t);if(n!==null){if(t=n.tag,t===13){if(t=Cu(n),t!==null){e.blockedOn=t,Du(e.priority,function(){Au(n)});return}}else if(t===3&&n.stateNode.current.memoizedState.isDehydrated){e.blockedOn=n.tag===3?n.stateNode.containerInfo:null;return}}}e.blockedOn=null}function Qr(e){if(e.blockedOn!==null)return!1;for(var t=e.targetContainers;0<t.length;){var n=Qo(e.domEventName,e.eventSystemFlags,t[0],e.nativeEvent);if(n===null){n=e.nativeEvent;var r=new n.constructor(n.type,n);Io=r,n.target.dispatchEvent(r),Io=null}else return t=vr(n),t!==null&&Ds(t),e.blockedOn=n,!1;t.shift()}return!0}function Dl(e,t,n){Qr(e)&&n.delete(t)}function Rf(){Wo=!1,vt!==null&&Qr(vt)&&(vt=null),xt!==null&&Qr(xt)&&(xt=null),wt!==null&&Qr(wt)&&(wt=null),tr.forEach(Dl),nr.forEach(Dl)}function Ln(e,t){e.blockedOn===t&&(e.blockedOn=null,Wo||(Wo=!0,Ee.unstable_scheduleCallback(Ee.unstable_NormalPriority,Rf)))}function rr(e){function t(i){return Ln(i,e)}if(0<Rr.length){Ln(Rr[0],e);for(var n=1;n<Rr.length;n++){var r=Rr[n];r.blockedOn===e&&(r.blockedOn=null)}}for(vt!==null&&Ln(vt,e),xt!==null&&Ln(xt,e),wt!==null&&Ln(wt,e),tr.forEach(t),nr.forEach(t),n=0;n<mt.length;n++)r=mt[n],r.blockedOn===e&&(r.blockedOn=null);for(;0<mt.length&&(n=mt[0],n.blockedOn===null);)Uu(n),n.blockedOn===null&&mt.shift()}var mn=ut.ReactCurrentBatchConfig,ci=!0;function Lf(e,t,n,r){var i=D,o=mn.transition;mn.transition=null;try{D=1,Us(e,t,n,r)}finally{D=i,mn.transition=o}}function Of(e,t,n,r){var i=D,o=mn.transition;mn.transition=null;try{D=4,Us(e,t,n,r)}finally{D=i,mn.transition=o}}function Us(e,t,n,r){if(ci){var i=Qo(e,t,n,r);if(i===null)mo(e,t,r,di,n),Ml(e,r);else if(Tf(i,e,t,n,r))r.stopPropagation();else if(Ml(e,r),t&4&&-1<Pf.indexOf(e)){for(;i!==null;){var o=vr(i);if(o!==null&&Fu(o),o=Qo(e,t,n,r),o===null&&mo(e,t,r,di,n),o===i)break;i=o}i!==null&&r.stopPropagation()}else mo(e,t,r,null,n)}}var di=null;function Qo(e,t,n,r){if(di=null,e=Fs(r),e=Ot(e),e!==null)if(t=Yt(e),t===null)e=null;else if(n=t.tag,n===13){if(e=Cu(t),e!==null)return e;e=null}else if(n===3){if(t.stateNode.current.memoizedState.isDehydrated)return t.tag===3?t.stateNode.containerInfo:null;e=null}else t!==e&&(e=null);return di=e,null}function Iu(e){switch(e){case"cancel":case"click":case"close":case"contextmenu":case"copy":case"cut":case"auxclick":case"dblclick":case"dragend":case"dragstart":case"drop":case"focusin":case"focusout":case"input":case"invalid":case"keydown":case"keypress":case"keyup":case"mousedown":case"mouseup":case"paste":case"pause":case"play":case"pointercancel":case"pointerdown":case"pointerup":case"ratechange":case"reset":case"resize":case"seeked":case"submit":case"touchcancel":case"touchend":case"touchstart":case"volumechange":case"change":case"selectionchange":case"textInput":case"compositionstart":case"compositionend":case"compositionupdate":case"beforeblur":case"afterblur":case"beforeinput":case"blur":case"fullscreenchange":case"focus":case"hashchange":case"popstate":case"select":case"selectstart":return 1;case"drag":case"dragenter":case"dragexit":case"dragleave":case"dragover":case"mousemove":case"mouseout":case"mouseover":case"pointermove":case"pointerout":case"pointerover":case"scroll":case"toggle":case"touchmove":case"wheel":case"mouseenter":case"mouseleave":case"pointerenter":case"pointerleave":return 4;case"message":switch(kf()){case As:return 1;case Tu:return 4;case ai:case Sf:return 16;case Ru:return 536870912;default:return 16}default:return 16}}var gt=null,Is=null,Kr=null;function $u(){if(Kr)return Kr;var e,t=Is,n=t.length,r,i="value"in gt?gt.value:gt.textContent,o=i.length;for(e=0;e<n&&t[e]===i[e];e++);var s=n-e;for(r=1;r<=s&&t[n-r]===i[o-r];r++);return Kr=i.slice(e,1<r?1-r:void 0)}function Yr(e){var t=e.keyCode;return"charCode"in e?(e=e.charCode,e===0&&t===13&&(e=13)):e=t,e===10&&(e=13),32<=e||e===13?e:0}function Lr(){return!0}function Ul(){return!1}function _e(e){function t(n,r,i,o,s){this._reactName=n,this._targetInst=i,this.type=r,this.nativeEvent=o,this.target=s,this.currentTarget=null;for(var l in e)e.hasOwnProperty(l)&&(n=e[l],this[l]=n?n(o):o[l]);return this.isDefaultPrevented=(o.defaultPrevented!=null?o.defaultPrevented:o.returnValue===!1)?Lr:Ul,this.isPropagationStopped=Ul,this}return Y(t.prototype,{preventDefault:function(){this.defaultPrevented=!0;var n=this.nativeEvent;n&&(n.preventDefault?n.preventDefault():typeof n.returnValue!="unknown"&&(n.returnValue=!1),this.isDefaultPrevented=Lr)},stopPropagation:function(){var n=this.nativeEvent;n&&(n.stopPropagation?n.stopPropagation():typeof n.cancelBubble!="unknown"&&(n.cancelBubble=!0),this.isPropagationStopped=Lr)},persist:function(){},isPersistent:Lr}),t}var En={eventPhase:0,bubbles:0,cancelable:0,timeStamp:function(e){return e.timeStamp||Date.now()},defaultPrevented:0,isTrusted:0},$s=_e(En),yr=Y({},En,{view:0,detail:0}),Ff=_e(yr),io,oo,On,Ri=Y({},yr,{screenX:0,screenY:0,clientX:0,clientY:0,pageX:0,pageY:0,ctrlKey:0,shiftKey:0,altKey:0,metaKey:0,getModifierState:Bs,button:0,buttons:0,relatedTarget:function(e){return e.relatedTarget===void 0?e.fromElement===e.srcElement?e.toElement:e.fromElement:e.relatedTarget},movementX:function(e){return"movementX"in e?e.movementX:(e!==On&&(On&&e.type==="mousemove"?(io=e.screenX-On.screenX,oo=e.screenY-On.screenY):oo=io=0,On=e),io)},movementY:function(e){return"movementY"in e?e.movementY:oo}}),Il=_e(Ri),Af=Y({},Ri,{dataTransfer:0}),Mf=_e(Af),Df=Y({},yr,{relatedTarget:0}),so=_e(Df),Uf=Y({},En,{animationName:0,elapsedTime:0,pseudoElement:0}),If=_e(Uf),$f=Y({},En,{clipboardData:function(e){return"clipboardData"in e?e.clipboardData:window.clipboardData}}),Bf=_e($f),Hf=Y({},En,{data:0}),$l=_e(Hf),Vf={Esc:"Escape",Spacebar:" ",Left:"ArrowLeft",Up:"ArrowUp",Right:"ArrowRight",Down:"ArrowDown",Del:"Delete",Win:"OS",Menu:"ContextMenu",Apps:"ContextMenu",Scroll:"ScrollLock",MozPrintableKey:"Unidentified"},Wf={8:"Backspace",9:"Tab",12:"Clear",13:"Enter",16:"Shift",17:"Control",18:"Alt",19:"Pause",20:"CapsLock",27:"Escape",32:" ",33:"PageUp",34:"PageDown",35:"End",36:"Home",37:"ArrowLeft",38:"ArrowUp",39:"ArrowRight",40:"ArrowDown",45:"Insert",46:"Delete",112:"F1",113:"F2",114:"F3",115:"F4",116:"F5",117:"F6",118:"F7",119:"F8",120:"F9",121:"F10",122:"F11",123:"F12",144:"NumLock",145:"ScrollLock",224:"Meta"},Qf={Alt:"altKey",Control:"ctrlKey",Meta:"metaKey",Shift:"shiftKey"};function Kf(e){var t=this.nativeEvent;return t.getModifierState?t.getModifierState(e):(e=Qf[e])?!!t[e]:!1}function Bs(){return Kf}var Yf=Y({},yr,{key:function(e){if(e.key){var t=Vf[e.key]||e.key;if(t!=="Unidentified")return t}return e.type==="keypress"?(e=Yr(e),e===13?"Enter":String.fromCharCode(e)):e.type==="keydown"||e.type==="keyup"?Wf[e.keyCode]||"Unidentified":""},code:0,location:0,ctrlKey:0,shiftKey:0,altKey:0,metaKey:0,repeat:0,locale:0,getModifierState:Bs,charCode:function(e){return e.type==="keypress"?Yr(e):0},keyCode:function(e){return e.type==="keydown"||e.type==="keyup"?e.keyCode:0},which:function(e){return e.type==="keypress"?Yr(e):e.type==="keydown"||e.type==="keyup"?e.keyCode:0}}),Xf=_e(Yf),qf=Y({},Ri,{pointerId:0,width:0,height:0,pressure:0,tangentialPressure:0,tiltX:0,tiltY:0,twist:0,pointerType:0,isPrimary:0}),Bl=_e(qf),Jf=Y({},yr,{touches:0,targetTouches:0,changedTouches:0,altKey:0,metaKey:0,ctrlKey:0,shiftKey:0,getModifierState:Bs}),Gf=_e(Jf),Zf=Y({},En,{propertyName:0,elapsedTime:0,pseudoElement:0}),ep=_e(Zf),tp=Y({},Ri,{deltaX:function(e){return"deltaX"in e?e.deltaX:"wheelDeltaX"in e?-e.wheelDeltaX:0},deltaY:function(e){return"deltaY"in e?e.deltaY:"wheelDeltaY"in e?-e.wheelDeltaY:"wheelDelta"in e?-e.wheelDelta:0},deltaZ:0,deltaMode:0}),np=_e(tp),rp=[9,13,27,32],Hs=ot&&"CompositionEvent"in window,Wn=null;ot&&"documentMode"in document&&(Wn=document.documentMode);var ip=ot&&"TextEvent"in window&&!Wn,Bu=ot&&(!Hs||Wn&&8<Wn&&11>=Wn),Hl=" ",Vl=!1;function Hu(e,t){switch(e){case"keyup":return rp.indexOf(t.keyCode)!==-1;case"keydown":return t.keyCode!==229;case"keypress":case"mousedown":case"focusout":return!0;default:return!1}}function Vu(e){return e=e.detail,typeof e=="object"&&"data"in e?e.data:null}var en=!1;function op(e,t){switch(e){case"compositionend":return Vu(t);case"keypress":return t.which!==32?null:(Vl=!0,Hl);case"textInput":return e=t.data,e===Hl&&Vl?null:e;default:return null}}function sp(e,t){if(en)return e==="compositionend"||!Hs&&Hu(e,t)?(e=$u(),Kr=Is=gt=null,en=!1,e):null;switch(e){case"paste":return null;case"keypress":if(!(t.ctrlKey||t.altKey||t.metaKey)||t.ctrlKey&&t.altKey){if(t.char&&1<t.char.length)return t.char;if(t.which)return String.fromCharCode(t.which)}return null;case"compositionend":return Bu&&t.locale!=="ko"?null:t.data;default:return null}}var lp={color:!0,date:!0,datetime:!0,"datetime-local":!0,email:!0,month:!0,number:!0,password:!0,range:!0,search:!0,tel:!0,text:!0,time:!0,url:!0,week:!0};function Wl(e){var t=e&&e.nodeName&&e.nodeName.toLowerCase();return t==="input"?!!lp[e.type]:t==="textarea"}function Wu(e,t,n,r){Su(r),t=fi(t,"onChange"),0<t.length&&(n=new $s("onChange","change",null,n,r),e.push({event:n,listeners:t}))}var Qn=null,ir=null;function ap(e){nc(e,0)}function Li(e){var t=rn(e);if(hu(t))return e}function up(e,t){if(e==="change")return t}var Qu=!1;if(ot){var lo;if(ot){var ao="oninput"in document;if(!ao){var Ql=document.createElement("div");Ql.setAttribute("oninput","return;"),ao=typeof Ql.oninput=="function"}lo=ao}else lo=!1;Qu=lo&&(!document.documentMode||9<document.documentMode)}function Kl(){Qn&&(Qn.detachEvent("onpropertychange",Ku),ir=Qn=null)}function Ku(e){if(e.propertyName==="value"&&Li(ir)){var t=[];Wu(t,ir,e,Fs(e)),Eu(ap,t)}}function cp(e,t,n){e==="focusin"?(Kl(),Qn=t,ir=n,Qn.attachEvent("onpropertychange",Ku)):e==="focusout"&&Kl()}function dp(e){if(e==="selectionchange"||e==="keyup"||e==="keydown")return Li(ir)}function fp(e,t){if(e==="click")return Li(t)}function pp(e,t){if(e==="input"||e==="change")return Li(t)}function mp(e,t){return e===t&&(e!==0||1/e===1/t)||e!==e&&t!==t}var Ve=typeof Object.is=="function"?Object.is:mp;function or(e,t){if(Ve(e,t))return!0;if(typeof e!="object"||e===null||typeof t!="object"||t===null)return!1;var n=Object.keys(e),r=Object.keys(t);if(n.length!==r.length)return!1;for(r=0;r<n.length;r++){var i=n[r];if(!_o.call(t,i)||!Ve(e[i],t[i]))return!1}return!0}function Yl(e){for(;e&&e.firstChild;)e=e.firstChild;return e}function Xl(e,t){var n=Yl(e);e=0;for(var r;n;){if(n.nodeType===3){if(r=e+n.textContent.length,e<=t&&r>=t)return{node:n,offset:t-e};e=r}e:{for(;n;){if(n.nextSibling){n=n.nextSibling;break e}n=n.parentNode}n=void 0}n=Yl(n)}}function Yu(e,t){return e&&t?e===t?!0:e&&e.nodeType===3?!1:t&&t.nodeType===3?Yu(e,t.parentNode):"contains"in e?e.contains(t):e.compareDocumentPosition?!!(e.compareDocumentPosition(t)&16):!1:!1}function Xu(){for(var e=window,t=oi();t instanceof e.HTMLIFrameElement;){try{var n=typeof t.contentWindow.location.href=="string"}catch{n=!1}if(n)e=t.contentWindow;else break;t=oi(e.document)}return t}function Vs(e){var t=e&&e.nodeName&&e.nodeName.toLowerCase();return t&&(t==="input"&&(e.type==="text"||e.type==="search"||e.type==="tel"||e.type==="url"||e.type==="password")||t==="textarea"||e.contentEditable==="true")}function hp(e){var t=Xu(),n=e.focusedElem,r=e.selectionRange;if(t!==n&&n&&n.ownerDocument&&Yu(n.ownerDocument.documentElement,n)){if(r!==null&&Vs(n)){if(t=r.start,e=r.end,e===void 0&&(e=t),"selectionStart"in n)n.selectionStart=t,n.selectionEnd=Math.min(e,n.value.length);else if(e=(t=n.ownerDocument||document)&&t.defaultView||window,e.getSelection){e=e.getSelection();var i=n.textContent.length,o=Math.min(r.start,i);r=r.end===void 0?o:Math.min(r.end,i),!e.extend&&o>r&&(i=r,r=o,o=i),i=Xl(n,o);var s=Xl(n,r);i&&s&&(e.rangeCount!==1||e.anchorNode!==i.node||e.anchorOffset!==i.offset||e.focusNode!==s.node||e.focusOffset!==s.offset)&&(t=t.createRange(),t.setStart(i.node,i.offset),e.removeAllRanges(),o>r?(e.addRange(t),e.extend(s.node,s.offset)):(t.setEnd(s.node,s.offset),e.addRange(t)))}}for(t=[],e=n;e=e.parentNode;)e.nodeType===1&&t.push({element:e,left:e.scrollLeft,top:e.scrollTop});for(typeof n.focus=="function"&&n.focus(),n=0;n<t.length;n++)e=t[n],e.element.scrollLeft=e.left,e.element.scrollTop=e.top}}var gp=ot&&"documentMode"in document&&11>=document.documentMode,tn=null,Ko=null,Kn=null,Yo=!1;function ql(e,t,n){var r=n.window===n?n.document:n.nodeType===9?n:n.ownerDocument;Yo||tn==null||tn!==oi(r)||(r=tn,"selectionStart"in r&&Vs(r)?r={start:r.selectionStart,end:r.selectionEnd}:(r=(r.ownerDocument&&r.ownerDocument.defaultView||window).getSelection(),r={anchorNode:r.anchorNode,anchorOffset:r.anchorOffset,focusNode:r.focusNode,focusOffset:r.focusOffset}),Kn&&or(Kn,r)||(Kn=r,r=fi(Ko,"onSelect"),0<r.length&&(t=new $s("onSelect","select",null,t,n),e.push({event:t,listeners:r}),t.target=tn)))}function Or(e,t){var n={};return n[e.toLowerCase()]=t.toLowerCase(),n["Webkit"+e]="webkit"+t,n["Moz"+e]="moz"+t,n}var nn={animationend:Or("Animation","AnimationEnd"),animationiteration:Or("Animation","AnimationIteration"),animationstart:Or("Animation","AnimationStart"),transitionend:Or("Transition","TransitionEnd")},uo={},qu={};ot&&(qu=document.createElement("div").style,"AnimationEvent"in window||(delete nn.animationend.animation,delete nn.animationiteration.animation,delete nn.animationstart.animation),"TransitionEvent"in window||delete nn.transitionend.transition);function Oi(e){if(uo[e])return uo[e];if(!nn[e])return e;var t=nn[e],n;for(n in t)if(t.hasOwnProperty(n)&&n in qu)return uo[e]=t[n];return e}var Ju=Oi("animationend"),Gu=Oi("animationiteration"),Zu=Oi("animationstart"),ec=Oi("transitionend"),tc=new Map,Jl="abort auxClick cancel canPlay canPlayThrough click close contextMenu copy cut drag dragEnd dragEnter dragExit dragLeave dragOver dragStart drop durationChange emptied encrypted ended error gotPointerCapture input invalid keyDown keyPress keyUp load loadedData loadedMetadata loadStart lostPointerCapture mouseDown mouseMove mouseOut mouseOver mouseUp paste pause play playing pointerCancel pointerDown pointerMove pointerOut pointerOver pointerUp progress rateChange reset resize seeked seeking stalled submit suspend timeUpdate touchCancel touchEnd touchStart volumeChange scroll toggle touchMove waiting wheel".split(" ");function _t(e,t){tc.set(e,t),Kt(t,[e])}for(var co=0;co<Jl.length;co++){var fo=Jl[co],yp=fo.toLowerCase(),vp=fo[0].toUpperCase()+fo.slice(1);_t(yp,"on"+vp)}_t(Ju,"onAnimationEnd");_t(Gu,"onAnimationIteration");_t(Zu,"onAnimationStart");_t("dblclick","onDoubleClick");_t("focusin","onFocus");_t("focusout","onBlur");_t(ec,"onTransitionEnd");yn("onMouseEnter",["mouseout","mouseover"]);yn("onMouseLeave",["mouseout","mouseover"]);yn("onPointerEnter",["pointerout","pointerover"]);yn("onPointerLeave",["pointerout","pointerover"]);Kt("onChange","change click focusin focusout input keydown keyup selectionchange".split(" "));Kt("onSelect","focusout contextmenu dragend focusin keydown keyup mousedown mouseup selectionchange".split(" "));Kt("onBeforeInput",["compositionend","keypress","textInput","paste"]);Kt("onCompositionEnd","compositionend focusout keydown keypress keyup mousedown".split(" "));Kt("onCompositionStart","compositionstart focusout keydown keypress keyup mousedown".split(" "));Kt("onCompositionUpdate","compositionupdate focusout keydown keypress keyup mousedown".split(" "));var Bn="abort canplay canplaythrough durationchange emptied encrypted ended error loadeddata loadedmetadata loadstart pause play playing progress ratechange resize seeked seeking stalled suspend timeupdate volumechange waiting".split(" "),xp=new Set("cancel close invalid load scroll toggle".split(" ").concat(Bn));function Gl(e,t,n){var r=e.type||"unknown-event";e.currentTarget=n,yf(r,t,void 0,e),e.currentTarget=null}function nc(e,t){t=(t&4)!==0;for(var n=0;n<e.length;n++){var r=e[n],i=r.event;r=r.listeners;e:{var o=void 0;if(t)for(var s=r.length-1;0<=s;s--){var l=r[s],u=l.instance,c=l.currentTarget;if(l=l.listener,u!==o&&i.isPropagationStopped())break e;Gl(i,l,c),o=u}else for(s=0;s<r.length;s++){if(l=r[s],u=l.instance,c=l.currentTarget,l=l.listener,u!==o&&i.isPropagationStopped())break e;Gl(i,l,c),o=u}}}if(li)throw e=Ho,li=!1,Ho=null,e}function H(e,t){var n=t[Zo];n===void 0&&(n=t[Zo]=new Set);var r=e+"__bubble";n.has(r)||(rc(t,e,2,!1),n.add(r))}function po(e,t,n){var r=0;t&&(r|=4),rc(n,e,r,t)}var Fr="_reactListening"+Math.random().toString(36).slice(2);function sr(e){if(!e[Fr]){e[Fr]=!0,cu.forEach(function(n){n!=="selectionchange"&&(xp.has(n)||po(n,!1,e),po(n,!0,e))});var t=e.nodeType===9?e:e.ownerDocument;t===null||t[Fr]||(t[Fr]=!0,po("selectionchange",!1,t))}}function rc(e,t,n,r){switch(Iu(t)){case 1:var i=Lf;break;case 4:i=Of;break;default:i=Us}n=i.bind(null,t,n,e),i=void 0,!Bo||t!=="touchstart"&&t!=="touchmove"&&t!=="wheel"||(i=!0),r?i!==void 0?e.addEventListener(t,n,{capture:!0,passive:i}):e.addEventListener(t,n,!0):i!==void 0?e.addEventListener(t,n,{passive:i}):e.addEventListener(t,n,!1)}function mo(e,t,n,r,i){var o=r;if(!(t&1)&&!(t&2)&&r!==null)e:for(;;){if(r===null)return;var s=r.tag;if(s===3||s===4){var l=r.stateNode.containerInfo;if(l===i||l.nodeType===8&&l.parentNode===i)break;if(s===4)for(s=r.return;s!==null;){var u=s.tag;if((u===3||u===4)&&(u=s.stateNode.containerInfo,u===i||u.nodeType===8&&u.parentNode===i))return;s=s.return}for(;l!==null;){if(s=Ot(l),s===null)return;if(u=s.tag,u===5||u===6){r=o=s;continue e}l=l.parentNode}}r=r.return}Eu(function(){var c=o,p=Fs(n),g=[];e:{var y=tc.get(e);if(y!==void 0){var S=$s,m=e;switch(e){case"keypress":if(Yr(n)===0)break e;case"keydown":case"keyup":S=Xf;break;case"focusin":m="focus",S=so;break;case"focusout":m="blur",S=so;break;case"beforeblur":case"afterblur":S=so;break;case"click":if(n.button===2)break e;case"auxclick":case"dblclick":case"mousedown":case"mousemove":case"mouseup":case"mouseout":case"mouseover":case"contextmenu":S=Il;break;case"drag":case"dragend":case"dragenter":case"dragexit":case"dragleave":case"dragover":case"dragstart":case"drop":S=Mf;break;case"touchcancel":case"touchend":case"touchmove":case"touchstart":S=Gf;break;case Ju:case Gu:case Zu:S=If;break;case ec:S=ep;break;case"scroll":S=Ff;break;case"wheel":S=np;break;case"copy":case"cut":case"paste":S=Bf;break;case"gotpointercapture":case"lostpointercapture":case"pointercancel":case"pointerdown":case"pointermove":case"pointerout":case"pointerover":case"pointerup":S=Bl}var v=(t&4)!==0,w=!v&&e==="scroll",d=v?y!==null?y+"Capture":null:y;v=[];for(var f=c,h;f!==null;){h=f;var k=h.stateNode;if(h.tag===5&&k!==null&&(h=k,d!==null&&(k=er(f,d),k!=null&&v.push(lr(f,k,h)))),w)break;f=f.return}0<v.length&&(y=new S(y,m,null,n,p),g.push({event:y,listeners:v}))}}if(!(t&7)){e:{if(y=e==="mouseover"||e==="pointerover",S=e==="mouseout"||e==="pointerout",y&&n!==Io&&(m=n.relatedTarget||n.fromElement)&&(Ot(m)||m[st]))break e;if((S||y)&&(y=p.window===p?p:(y=p.ownerDocument)?y.defaultView||y.parentWindow:window,S?(m=n.relatedTarget||n.toElement,S=c,m=m?Ot(m):null,m!==null&&(w=Yt(m),m!==w||m.tag!==5&&m.tag!==6)&&(m=null)):(S=null,m=c),S!==m)){if(v=Il,k="onMouseLeave",d="onMouseEnter",f="mouse",(e==="pointerout"||e==="pointerover")&&(v=Bl,k="onPointerLeave",d="onPointerEnter",f="pointer"),w=S==null?y:rn(S),h=m==null?y:rn(m),y=new v(k,f+"leave",S,n,p),y.target=w,y.relatedTarget=h,k=null,Ot(p)===c&&(v=new v(d,f+"enter",m,n,p),v.target=h,v.relatedTarget=w,k=v),w=k,S&&m)t:{for(v=S,d=m,f=0,h=v;h;h=Jt(h))f++;for(h=0,k=d;k;k=Jt(k))h++;for(;0<f-h;)v=Jt(v),f--;for(;0<h-f;)d=Jt(d),h--;for(;f--;){if(v===d||d!==null&&v===d.alternate)break t;v=Jt(v),d=Jt(d)}v=null}else v=null;S!==null&&Zl(g,y,S,v,!1),m!==null&&w!==null&&Zl(g,w,m,v,!0)}}e:{if(y=c?rn(c):window,S=y.nodeName&&y.nodeName.toLowerCase(),S==="select"||S==="input"&&y.type==="file")var j=up;else if(Wl(y))if(Qu)j=pp;else{j=dp;var _=cp}else(S=y.nodeName)&&S.toLowerCase()==="input"&&(y.type==="checkbox"||y.type==="radio")&&(j=fp);if(j&&(j=j(e,c))){Wu(g,j,n,p);break e}_&&_(e,y,c),e==="focusout"&&(_=y._wrapperState)&&_.controlled&&y.type==="number"&&Fo(y,"number",y.value)}switch(_=c?rn(c):window,e){case"focusin":(Wl(_)||_.contentEditable==="true")&&(tn=_,Ko=c,Kn=null);break;case"focusout":Kn=Ko=tn=null;break;case"mousedown":Yo=!0;break;case"contextmenu":case"mouseup":case"dragend":Yo=!1,ql(g,n,p);break;case"selectionchange":if(gp)break;case"keydown":case"keyup":ql(g,n,p)}var E;if(Hs)e:{switch(e){case"compositionstart":var z="onCompositionStart";break e;case"compositionend":z="onCompositionEnd";break e;case"compositionupdate":z="onCompositionUpdate";break e}z=void 0}else en?Hu(e,n)&&(z="onCompositionEnd"):e==="keydown"&&n.keyCode===229&&(z="onCompositionStart");z&&(Bu&&n.locale!=="ko"&&(en||z!=="onCompositionStart"?z==="onCompositionEnd"&&en&&(E=$u()):(gt=p,Is="value"in gt?gt.value:gt.textContent,en=!0)),_=fi(c,z),0<_.length&&(z=new $l(z,e,null,n,p),g.push({event:z,listeners:_}),E?z.data=E:(E=Vu(n),E!==null&&(z.data=E)))),(E=ip?op(e,n):sp(e,n))&&(c=fi(c,"onBeforeInput"),0<c.length&&(p=new $l("onBeforeInput","beforeinput",null,n,p),g.push({event:p,listeners:c}),p.data=E))}nc(g,t)})}function lr(e,t,n){return{instance:e,listener:t,currentTarget:n}}function fi(e,t){for(var n=t+"Capture",r=[];e!==null;){var i=e,o=i.stateNode;i.tag===5&&o!==null&&(i=o,o=er(e,n),o!=null&&r.unshift(lr(e,o,i)),o=er(e,t),o!=null&&r.push(lr(e,o,i))),e=e.return}return r}function Jt(e){if(e===null)return null;do e=e.return;while(e&&e.tag!==5);return e||null}function Zl(e,t,n,r,i){for(var o=t._reactName,s=[];n!==null&&n!==r;){var l=n,u=l.alternate,c=l.stateNode;if(u!==null&&u===r)break;l.tag===5&&c!==null&&(l=c,i?(u=er(n,o),u!=null&&s.unshift(lr(n,u,l))):i||(u=er(n,o),u!=null&&s.push(lr(n,u,l)))),n=n.return}s.length!==0&&e.push({event:t,listeners:s})}var wp=/\r\n?/g,kp=/\u0000|\uFFFD/g;function ea(e){return(typeof e=="string"?e:""+e).replace(wp,`
`).replace(kp,"")}function Ar(e,t,n){if(t=ea(t),ea(e)!==t&&n)throw Error(N(425))}function pi(){}var Xo=null,qo=null;function Jo(e,t){return e==="textarea"||e==="noscript"||typeof t.children=="string"||typeof t.children=="number"||typeof t.dangerouslySetInnerHTML=="object"&&t.dangerouslySetInnerHTML!==null&&t.dangerouslySetInnerHTML.__html!=null}var Go=typeof setTimeout=="function"?setTimeout:void 0,Sp=typeof clearTimeout=="function"?clearTimeout:void 0,ta=typeof Promise=="function"?Promise:void 0,Np=typeof queueMicrotask=="function"?queueMicrotask:typeof ta<"u"?function(e){return ta.resolve(null).then(e).catch(jp)}:Go;function jp(e){setTimeout(function(){throw e})}function ho(e,t){var n=t,r=0;do{var i=n.nextSibling;if(e.removeChild(n),i&&i.nodeType===8)if(n=i.data,n==="/$"){if(r===0){e.removeChild(i),rr(t);return}r--}else n!=="$"&&n!=="$?"&&n!=="$!"||r++;n=i}while(n);rr(t)}function kt(e){for(;e!=null;e=e.nextSibling){var t=e.nodeType;if(t===1||t===3)break;if(t===8){if(t=e.data,t==="$"||t==="$!"||t==="$?")break;if(t==="/$")return null}}return e}function na(e){e=e.previousSibling;for(var t=0;e;){if(e.nodeType===8){var n=e.data;if(n==="$"||n==="$!"||n==="$?"){if(t===0)return e;t--}else n==="/$"&&t++}e=e.previousSibling}return null}var Cn=Math.random().toString(36).slice(2),Je="__reactFiber$"+Cn,ar="__reactProps$"+Cn,st="__reactContainer$"+Cn,Zo="__reactEvents$"+Cn,bp="__reactListeners$"+Cn,Ep="__reactHandles$"+Cn;function Ot(e){var t=e[Je];if(t)return t;for(var n=e.parentNode;n;){if(t=n[st]||n[Je]){if(n=t.alternate,t.child!==null||n!==null&&n.child!==null)for(e=na(e);e!==null;){if(n=e[Je])return n;e=na(e)}return t}e=n,n=e.parentNode}return null}function vr(e){return e=e[Je]||e[st],!e||e.tag!==5&&e.tag!==6&&e.tag!==13&&e.tag!==3?null:e}function rn(e){if(e.tag===5||e.tag===6)return e.stateNode;throw Error(N(33))}function Fi(e){return e[ar]||null}var es=[],on=-1;function zt(e){return{current:e}}function V(e){0>on||(e.current=es[on],es[on]=null,on--)}function $(e,t){on++,es[on]=e.current,e.current=t}var Ct={},ue=zt(Ct),ve=zt(!1),$t=Ct;function vn(e,t){var n=e.type.contextTypes;if(!n)return Ct;var r=e.stateNode;if(r&&r.__reactInternalMemoizedUnmaskedChildContext===t)return r.__reactInternalMemoizedMaskedChildContext;var i={},o;for(o in n)i[o]=t[o];return r&&(e=e.stateNode,e.__reactInternalMemoizedUnmaskedChildContext=t,e.__reactInternalMemoizedMaskedChildContext=i),i}function xe(e){return e=e.childContextTypes,e!=null}function mi(){V(ve),V(ue)}function ra(e,t,n){if(ue.current!==Ct)throw Error(N(168));$(ue,t),$(ve,n)}function ic(e,t,n){var r=e.stateNode;if(t=t.childContextTypes,typeof r.getChildContext!="function")return n;r=r.getChildContext();for(var i in r)if(!(i in t))throw Error(N(108,cf(e)||"Unknown",i));return Y({},n,r)}function hi(e){return e=(e=e.stateNode)&&e.__reactInternalMemoizedMergedChildContext||Ct,$t=ue.current,$(ue,e),$(ve,ve.current),!0}function ia(e,t,n){var r=e.stateNode;if(!r)throw Error(N(169));n?(e=ic(e,t,$t),r.__reactInternalMemoizedMergedChildContext=e,V(ve),V(ue),$(ue,e)):V(ve),$(ve,n)}var tt=null,Ai=!1,go=!1;function oc(e){tt===null?tt=[e]:tt.push(e)}function Cp(e){Ai=!0,oc(e)}function Pt(){if(!go&&tt!==null){go=!0;var e=0,t=D;try{var n=tt;for(D=1;e<n.length;e++){var r=n[e];do r=r(!0);while(r!==null)}tt=null,Ai=!1}catch(i){throw tt!==null&&(tt=tt.slice(e+1)),Pu(As,Pt),i}finally{D=t,go=!1}}return null}var sn=[],ln=0,gi=null,yi=0,Pe=[],Te=0,Bt=null,nt=1,rt="";function Rt(e,t){sn[ln++]=yi,sn[ln++]=gi,gi=e,yi=t}function sc(e,t,n){Pe[Te++]=nt,Pe[Te++]=rt,Pe[Te++]=Bt,Bt=e;var r=nt;e=rt;var i=32-Be(r)-1;r&=~(1<<i),n+=1;var o=32-Be(t)+i;if(30<o){var s=i-i%5;o=(r&(1<<s)-1).toString(32),r>>=s,i-=s,nt=1<<32-Be(t)+i|n<<i|r,rt=o+e}else nt=1<<o|n<<i|r,rt=e}function Ws(e){e.return!==null&&(Rt(e,1),sc(e,1,0))}function Qs(e){for(;e===gi;)gi=sn[--ln],sn[ln]=null,yi=sn[--ln],sn[ln]=null;for(;e===Bt;)Bt=Pe[--Te],Pe[Te]=null,rt=Pe[--Te],Pe[Te]=null,nt=Pe[--Te],Pe[Te]=null}var be=null,je=null,W=!1,$e=null;function lc(e,t){var n=Re(5,null,null,0);n.elementType="DELETED",n.stateNode=t,n.return=e,t=e.deletions,t===null?(e.deletions=[n],e.flags|=16):t.push(n)}function oa(e,t){switch(e.tag){case 5:var n=e.type;return t=t.nodeType!==1||n.toLowerCase()!==t.nodeName.toLowerCase()?null:t,t!==null?(e.stateNode=t,be=e,je=kt(t.firstChild),!0):!1;case 6:return t=e.pendingProps===""||t.nodeType!==3?null:t,t!==null?(e.stateNode=t,be=e,je=null,!0):!1;case 13:return t=t.nodeType!==8?null:t,t!==null?(n=Bt!==null?{id:nt,overflow:rt}:null,e.memoizedState={dehydrated:t,treeContext:n,retryLane:1073741824},n=Re(18,null,null,0),n.stateNode=t,n.return=e,e.child=n,be=e,je=null,!0):!1;default:return!1}}function ts(e){return(e.mode&1)!==0&&(e.flags&128)===0}function ns(e){if(W){var t=je;if(t){var n=t;if(!oa(e,t)){if(ts(e))throw Error(N(418));t=kt(n.nextSibling);var r=be;t&&oa(e,t)?lc(r,n):(e.flags=e.flags&-4097|2,W=!1,be=e)}}else{if(ts(e))throw Error(N(418));e.flags=e.flags&-4097|2,W=!1,be=e}}}function sa(e){for(e=e.return;e!==null&&e.tag!==5&&e.tag!==3&&e.tag!==13;)e=e.return;be=e}function Mr(e){if(e!==be)return!1;if(!W)return sa(e),W=!0,!1;var t;if((t=e.tag!==3)&&!(t=e.tag!==5)&&(t=e.type,t=t!=="head"&&t!=="body"&&!Jo(e.type,e.memoizedProps)),t&&(t=je)){if(ts(e))throw ac(),Error(N(418));for(;t;)lc(e,t),t=kt(t.nextSibling)}if(sa(e),e.tag===13){if(e=e.memoizedState,e=e!==null?e.dehydrated:null,!e)throw Error(N(317));e:{for(e=e.nextSibling,t=0;e;){if(e.nodeType===8){var n=e.data;if(n==="/$"){if(t===0){je=kt(e.nextSibling);break e}t--}else n!=="$"&&n!=="$!"&&n!=="$?"||t++}e=e.nextSibling}je=null}}else je=be?kt(e.stateNode.nextSibling):null;return!0}function ac(){for(var e=je;e;)e=kt(e.nextSibling)}function xn(){je=be=null,W=!1}function Ks(e){$e===null?$e=[e]:$e.push(e)}var _p=ut.ReactCurrentBatchConfig;function Fn(e,t,n){if(e=n.ref,e!==null&&typeof e!="function"&&typeof e!="object"){if(n._owner){if(n=n._owner,n){if(n.tag!==1)throw Error(N(309));var r=n.stateNode}if(!r)throw Error(N(147,e));var i=r,o=""+e;return t!==null&&t.ref!==null&&typeof t.ref=="function"&&t.ref._stringRef===o?t.ref:(t=function(s){var l=i.refs;s===null?delete l[o]:l[o]=s},t._stringRef=o,t)}if(typeof e!="string")throw Error(N(284));if(!n._owner)throw Error(N(290,e))}return e}function Dr(e,t){throw e=Object.prototype.toString.call(t),Error(N(31,e==="[object Object]"?"object with keys {"+Object.keys(t).join(", ")+"}":e))}function la(e){var t=e._init;return t(e._payload)}function uc(e){function t(d,f){if(e){var h=d.deletions;h===null?(d.deletions=[f],d.flags|=16):h.push(f)}}function n(d,f){if(!e)return null;for(;f!==null;)t(d,f),f=f.sibling;return null}function r(d,f){for(d=new Map;f!==null;)f.key!==null?d.set(f.key,f):d.set(f.index,f),f=f.sibling;return d}function i(d,f){return d=bt(d,f),d.index=0,d.sibling=null,d}function o(d,f,h){return d.index=h,e?(h=d.alternate,h!==null?(h=h.index,h<f?(d.flags|=2,f):h):(d.flags|=2,f)):(d.flags|=1048576,f)}function s(d){return e&&d.alternate===null&&(d.flags|=2),d}function l(d,f,h,k){return f===null||f.tag!==6?(f=No(h,d.mode,k),f.return=d,f):(f=i(f,h),f.return=d,f)}function u(d,f,h,k){var j=h.type;return j===Zt?p(d,f,h.props.children,k,h.key):f!==null&&(f.elementType===j||typeof j=="object"&&j!==null&&j.$$typeof===ft&&la(j)===f.type)?(k=i(f,h.props),k.ref=Fn(d,f,h),k.return=d,k):(k=ti(h.type,h.key,h.props,null,d.mode,k),k.ref=Fn(d,f,h),k.return=d,k)}function c(d,f,h,k){return f===null||f.tag!==4||f.stateNode.containerInfo!==h.containerInfo||f.stateNode.implementation!==h.implementation?(f=jo(h,d.mode,k),f.return=d,f):(f=i(f,h.children||[]),f.return=d,f)}function p(d,f,h,k,j){return f===null||f.tag!==7?(f=Ut(h,d.mode,k,j),f.return=d,f):(f=i(f,h),f.return=d,f)}function g(d,f,h){if(typeof f=="string"&&f!==""||typeof f=="number")return f=No(""+f,d.mode,h),f.return=d,f;if(typeof f=="object"&&f!==null){switch(f.$$typeof){case Cr:return h=ti(f.type,f.key,f.props,null,d.mode,h),h.ref=Fn(d,null,f),h.return=d,h;case Gt:return f=jo(f,d.mode,h),f.return=d,f;case ft:var k=f._init;return g(d,k(f._payload),h)}if(In(f)||Pn(f))return f=Ut(f,d.mode,h,null),f.return=d,f;Dr(d,f)}return null}function y(d,f,h,k){var j=f!==null?f.key:null;if(typeof h=="string"&&h!==""||typeof h=="number")return j!==null?null:l(d,f,""+h,k);if(typeof h=="object"&&h!==null){switch(h.$$typeof){case Cr:return h.key===j?u(d,f,h,k):null;case Gt:return h.key===j?c(d,f,h,k):null;case ft:return j=h._init,y(d,f,j(h._payload),k)}if(In(h)||Pn(h))return j!==null?null:p(d,f,h,k,null);Dr(d,h)}return null}function S(d,f,h,k,j){if(typeof k=="string"&&k!==""||typeof k=="number")return d=d.get(h)||null,l(f,d,""+k,j);if(typeof k=="object"&&k!==null){switch(k.$$typeof){case Cr:return d=d.get(k.key===null?h:k.key)||null,u(f,d,k,j);case Gt:return d=d.get(k.key===null?h:k.key)||null,c(f,d,k,j);case ft:var _=k._init;return S(d,f,h,_(k._payload),j)}if(In(k)||Pn(k))return d=d.get(h)||null,p(f,d,k,j,null);Dr(f,k)}return null}function m(d,f,h,k){for(var j=null,_=null,E=f,z=f=0,I=null;E!==null&&z<h.length;z++){E.index>z?(I=E,E=null):I=E.sibling;var L=y(d,E,h[z],k);if(L===null){E===null&&(E=I);break}e&&E&&L.alternate===null&&t(d,E),f=o(L,f,z),_===null?j=L:_.sibling=L,_=L,E=I}if(z===h.length)return n(d,E),W&&Rt(d,z),j;if(E===null){for(;z<h.length;z++)E=g(d,h[z],k),E!==null&&(f=o(E,f,z),_===null?j=E:_.sibling=E,_=E);return W&&Rt(d,z),j}for(E=r(d,E);z<h.length;z++)I=S(E,d,z,h[z],k),I!==null&&(e&&I.alternate!==null&&E.delete(I.key===null?z:I.key),f=o(I,f,z),_===null?j=I:_.sibling=I,_=I);return e&&E.forEach(function(me){return t(d,me)}),W&&Rt(d,z),j}function v(d,f,h,k){var j=Pn(h);if(typeof j!="function")throw Error(N(150));if(h=j.call(h),h==null)throw Error(N(151));for(var _=j=null,E=f,z=f=0,I=null,L=h.next();E!==null&&!L.done;z++,L=h.next()){E.index>z?(I=E,E=null):I=E.sibling;var me=y(d,E,L.value,k);if(me===null){E===null&&(E=I);break}e&&E&&me.alternate===null&&t(d,E),f=o(me,f,z),_===null?j=me:_.sibling=me,_=me,E=I}if(L.done)return n(d,E),W&&Rt(d,z),j;if(E===null){for(;!L.done;z++,L=h.next())L=g(d,L.value,k),L!==null&&(f=o(L,f,z),_===null?j=L:_.sibling=L,_=L);return W&&Rt(d,z),j}for(E=r(d,E);!L.done;z++,L=h.next())L=S(E,d,z,L.value,k),L!==null&&(e&&L.alternate!==null&&E.delete(L.key===null?z:L.key),f=o(L,f,z),_===null?j=L:_.sibling=L,_=L);return e&&E.forEach(function(Qe){return t(d,Qe)}),W&&Rt(d,z),j}function w(d,f,h,k){if(typeof h=="object"&&h!==null&&h.type===Zt&&h.key===null&&(h=h.props.children),typeof h=="object"&&h!==null){switch(h.$$typeof){case Cr:e:{for(var j=h.key,_=f;_!==null;){if(_.key===j){if(j=h.type,j===Zt){if(_.tag===7){n(d,_.sibling),f=i(_,h.props.children),f.return=d,d=f;break e}}else if(_.elementType===j||typeof j=="object"&&j!==null&&j.$$typeof===ft&&la(j)===_.type){n(d,_.sibling),f=i(_,h.props),f.ref=Fn(d,_,h),f.return=d,d=f;break e}n(d,_);break}else t(d,_);_=_.sibling}h.type===Zt?(f=Ut(h.props.children,d.mode,k,h.key),f.return=d,d=f):(k=ti(h.type,h.key,h.props,null,d.mode,k),k.ref=Fn(d,f,h),k.return=d,d=k)}return s(d);case Gt:e:{for(_=h.key;f!==null;){if(f.key===_)if(f.tag===4&&f.stateNode.containerInfo===h.containerInfo&&f.stateNode.implementation===h.implementation){n(d,f.sibling),f=i(f,h.children||[]),f.return=d,d=f;break e}else{n(d,f);break}else t(d,f);f=f.sibling}f=jo(h,d.mode,k),f.return=d,d=f}return s(d);case ft:return _=h._init,w(d,f,_(h._payload),k)}if(In(h))return m(d,f,h,k);if(Pn(h))return v(d,f,h,k);Dr(d,h)}return typeof h=="string"&&h!==""||typeof h=="number"?(h=""+h,f!==null&&f.tag===6?(n(d,f.sibling),f=i(f,h),f.return=d,d=f):(n(d,f),f=No(h,d.mode,k),f.return=d,d=f),s(d)):n(d,f)}return w}var wn=uc(!0),cc=uc(!1),vi=zt(null),xi=null,an=null,Ys=null;function Xs(){Ys=an=xi=null}function qs(e){var t=vi.current;V(vi),e._currentValue=t}function rs(e,t,n){for(;e!==null;){var r=e.alternate;if((e.childLanes&t)!==t?(e.childLanes|=t,r!==null&&(r.childLanes|=t)):r!==null&&(r.childLanes&t)!==t&&(r.childLanes|=t),e===n)break;e=e.return}}function hn(e,t){xi=e,Ys=an=null,e=e.dependencies,e!==null&&e.firstContext!==null&&(e.lanes&t&&(ye=!0),e.firstContext=null)}function Fe(e){var t=e._currentValue;if(Ys!==e)if(e={context:e,memoizedValue:t,next:null},an===null){if(xi===null)throw Error(N(308));an=e,xi.dependencies={lanes:0,firstContext:e}}else an=an.next=e;return t}var Ft=null;function Js(e){Ft===null?Ft=[e]:Ft.push(e)}function dc(e,t,n,r){var i=t.interleaved;return i===null?(n.next=n,Js(t)):(n.next=i.next,i.next=n),t.interleaved=n,lt(e,r)}function lt(e,t){e.lanes|=t;var n=e.alternate;for(n!==null&&(n.lanes|=t),n=e,e=e.return;e!==null;)e.childLanes|=t,n=e.alternate,n!==null&&(n.childLanes|=t),n=e,e=e.return;return n.tag===3?n.stateNode:null}var pt=!1;function Gs(e){e.updateQueue={baseState:e.memoizedState,firstBaseUpdate:null,lastBaseUpdate:null,shared:{pending:null,interleaved:null,lanes:0},effects:null}}function fc(e,t){e=e.updateQueue,t.updateQueue===e&&(t.updateQueue={baseState:e.baseState,firstBaseUpdate:e.firstBaseUpdate,lastBaseUpdate:e.lastBaseUpdate,shared:e.shared,effects:e.effects})}function it(e,t){return{eventTime:e,lane:t,tag:0,payload:null,callback:null,next:null}}function St(e,t,n){var r=e.updateQueue;if(r===null)return null;if(r=r.shared,A&2){var i=r.pending;return i===null?t.next=t:(t.next=i.next,i.next=t),r.pending=t,lt(e,n)}return i=r.interleaved,i===null?(t.next=t,Js(r)):(t.next=i.next,i.next=t),r.interleaved=t,lt(e,n)}function Xr(e,t,n){if(t=t.updateQueue,t!==null&&(t=t.shared,(n&4194240)!==0)){var r=t.lanes;r&=e.pendingLanes,n|=r,t.lanes=n,Ms(e,n)}}function aa(e,t){var n=e.updateQueue,r=e.alternate;if(r!==null&&(r=r.updateQueue,n===r)){var i=null,o=null;if(n=n.firstBaseUpdate,n!==null){do{var s={eventTime:n.eventTime,lane:n.lane,tag:n.tag,payload:n.payload,callback:n.callback,next:null};o===null?i=o=s:o=o.next=s,n=n.next}while(n!==null);o===null?i=o=t:o=o.next=t}else i=o=t;n={baseState:r.baseState,firstBaseUpdate:i,lastBaseUpdate:o,shared:r.shared,effects:r.effects},e.updateQueue=n;return}e=n.lastBaseUpdate,e===null?n.firstBaseUpdate=t:e.next=t,n.lastBaseUpdate=t}function wi(e,t,n,r){var i=e.updateQueue;pt=!1;var o=i.firstBaseUpdate,s=i.lastBaseUpdate,l=i.shared.pending;if(l!==null){i.shared.pending=null;var u=l,c=u.next;u.next=null,s===null?o=c:s.next=c,s=u;var p=e.alternate;p!==null&&(p=p.updateQueue,l=p.lastBaseUpdate,l!==s&&(l===null?p.firstBaseUpdate=c:l.next=c,p.lastBaseUpdate=u))}if(o!==null){var g=i.baseState;s=0,p=c=u=null,l=o;do{var y=l.lane,S=l.eventTime;if((r&y)===y){p!==null&&(p=p.next={eventTime:S,lane:0,tag:l.tag,payload:l.payload,callback:l.callback,next:null});e:{var m=e,v=l;switch(y=t,S=n,v.tag){case 1:if(m=v.payload,typeof m=="function"){g=m.call(S,g,y);break e}g=m;break e;case 3:m.flags=m.flags&-65537|128;case 0:if(m=v.payload,y=typeof m=="function"?m.call(S,g,y):m,y==null)break e;g=Y({},g,y);break e;case 2:pt=!0}}l.callback!==null&&l.lane!==0&&(e.flags|=64,y=i.effects,y===null?i.effects=[l]:y.push(l))}else S={eventTime:S,lane:y,tag:l.tag,payload:l.payload,callback:l.callback,next:null},p===null?(c=p=S,u=g):p=p.next=S,s|=y;if(l=l.next,l===null){if(l=i.shared.pending,l===null)break;y=l,l=y.next,y.next=null,i.lastBaseUpdate=y,i.shared.pending=null}}while(!0);if(p===null&&(u=g),i.baseState=u,i.firstBaseUpdate=c,i.lastBaseUpdate=p,t=i.shared.interleaved,t!==null){i=t;do s|=i.lane,i=i.next;while(i!==t)}else o===null&&(i.shared.lanes=0);Vt|=s,e.lanes=s,e.memoizedState=g}}function ua(e,t,n){if(e=t.effects,t.effects=null,e!==null)for(t=0;t<e.length;t++){var r=e[t],i=r.callback;if(i!==null){if(r.callback=null,r=n,typeof i!="function")throw Error(N(191,i));i.call(r)}}}var xr={},Ze=zt(xr),ur=zt(xr),cr=zt(xr);function At(e){if(e===xr)throw Error(N(174));return e}function Zs(e,t){switch($(cr,t),$(ur,e),$(Ze,xr),e=t.nodeType,e){case 9:case 11:t=(t=t.documentElement)?t.namespaceURI:Mo(null,"");break;default:e=e===8?t.parentNode:t,t=e.namespaceURI||null,e=e.tagName,t=Mo(t,e)}V(Ze),$(Ze,t)}function kn(){V(Ze),V(ur),V(cr)}function pc(e){At(cr.current);var t=At(Ze.current),n=Mo(t,e.type);t!==n&&($(ur,e),$(Ze,n))}function el(e){ur.current===e&&(V(Ze),V(ur))}var Q=zt(0);function ki(e){for(var t=e;t!==null;){if(t.tag===13){var n=t.memoizedState;if(n!==null&&(n=n.dehydrated,n===null||n.data==="$?"||n.data==="$!"))return t}else if(t.tag===19&&t.memoizedProps.revealOrder!==void 0){if(t.flags&128)return t}else if(t.child!==null){t.child.return=t,t=t.child;continue}if(t===e)break;for(;t.sibling===null;){if(t.return===null||t.return===e)return null;t=t.return}t.sibling.return=t.return,t=t.sibling}return null}var yo=[];function tl(){for(var e=0;e<yo.length;e++)yo[e]._workInProgressVersionPrimary=null;yo.length=0}var qr=ut.ReactCurrentDispatcher,vo=ut.ReactCurrentBatchConfig,Ht=0,K=null,G=null,ee=null,Si=!1,Yn=!1,dr=0,zp=0;function oe(){throw Error(N(321))}function nl(e,t){if(t===null)return!1;for(var n=0;n<t.length&&n<e.length;n++)if(!Ve(e[n],t[n]))return!1;return!0}function rl(e,t,n,r,i,o){if(Ht=o,K=t,t.memoizedState=null,t.updateQueue=null,t.lanes=0,qr.current=e===null||e.memoizedState===null?Lp:Op,e=n(r,i),Yn){o=0;do{if(Yn=!1,dr=0,25<=o)throw Error(N(301));o+=1,ee=G=null,t.updateQueue=null,qr.current=Fp,e=n(r,i)}while(Yn)}if(qr.current=Ni,t=G!==null&&G.next!==null,Ht=0,ee=G=K=null,Si=!1,t)throw Error(N(300));return e}function il(){var e=dr!==0;return dr=0,e}function qe(){var e={memoizedState:null,baseState:null,baseQueue:null,queue:null,next:null};return ee===null?K.memoizedState=ee=e:ee=ee.next=e,ee}function Ae(){if(G===null){var e=K.alternate;e=e!==null?e.memoizedState:null}else e=G.next;var t=ee===null?K.memoizedState:ee.next;if(t!==null)ee=t,G=e;else{if(e===null)throw Error(N(310));G=e,e={memoizedState:G.memoizedState,baseState:G.baseState,baseQueue:G.baseQueue,queue:G.queue,next:null},ee===null?K.memoizedState=ee=e:ee=ee.next=e}return ee}function fr(e,t){return typeof t=="function"?t(e):t}function xo(e){var t=Ae(),n=t.queue;if(n===null)throw Error(N(311));n.lastRenderedReducer=e;var r=G,i=r.baseQueue,o=n.pending;if(o!==null){if(i!==null){var s=i.next;i.next=o.next,o.next=s}r.baseQueue=i=o,n.pending=null}if(i!==null){o=i.next,r=r.baseState;var l=s=null,u=null,c=o;do{var p=c.lane;if((Ht&p)===p)u!==null&&(u=u.next={lane:0,action:c.action,hasEagerState:c.hasEagerState,eagerState:c.eagerState,next:null}),r=c.hasEagerState?c.eagerState:e(r,c.action);else{var g={lane:p,action:c.action,hasEagerState:c.hasEagerState,eagerState:c.eagerState,next:null};u===null?(l=u=g,s=r):u=u.next=g,K.lanes|=p,Vt|=p}c=c.next}while(c!==null&&c!==o);u===null?s=r:u.next=l,Ve(r,t.memoizedState)||(ye=!0),t.memoizedState=r,t.baseState=s,t.baseQueue=u,n.lastRenderedState=r}if(e=n.interleaved,e!==null){i=e;do o=i.lane,K.lanes|=o,Vt|=o,i=i.next;while(i!==e)}else i===null&&(n.lanes=0);return[t.memoizedState,n.dispatch]}function wo(e){var t=Ae(),n=t.queue;if(n===null)throw Error(N(311));n.lastRenderedReducer=e;var r=n.dispatch,i=n.pending,o=t.memoizedState;if(i!==null){n.pending=null;var s=i=i.next;do o=e(o,s.action),s=s.next;while(s!==i);Ve(o,t.memoizedState)||(ye=!0),t.memoizedState=o,t.baseQueue===null&&(t.baseState=o),n.lastRenderedState=o}return[o,r]}function mc(){}function hc(e,t){var n=K,r=Ae(),i=t(),o=!Ve(r.memoizedState,i);if(o&&(r.memoizedState=i,ye=!0),r=r.queue,ol(vc.bind(null,n,r,e),[e]),r.getSnapshot!==t||o||ee!==null&&ee.memoizedState.tag&1){if(n.flags|=2048,pr(9,yc.bind(null,n,r,i,t),void 0,null),te===null)throw Error(N(349));Ht&30||gc(n,t,i)}return i}function gc(e,t,n){e.flags|=16384,e={getSnapshot:t,value:n},t=K.updateQueue,t===null?(t={lastEffect:null,stores:null},K.updateQueue=t,t.stores=[e]):(n=t.stores,n===null?t.stores=[e]:n.push(e))}function yc(e,t,n,r){t.value=n,t.getSnapshot=r,xc(t)&&wc(e)}function vc(e,t,n){return n(function(){xc(t)&&wc(e)})}function xc(e){var t=e.getSnapshot;e=e.value;try{var n=t();return!Ve(e,n)}catch{return!0}}function wc(e){var t=lt(e,1);t!==null&&He(t,e,1,-1)}function ca(e){var t=qe();return typeof e=="function"&&(e=e()),t.memoizedState=t.baseState=e,e={pending:null,interleaved:null,lanes:0,dispatch:null,lastRenderedReducer:fr,lastRenderedState:e},t.queue=e,e=e.dispatch=Rp.bind(null,K,e),[t.memoizedState,e]}function pr(e,t,n,r){return e={tag:e,create:t,destroy:n,deps:r,next:null},t=K.updateQueue,t===null?(t={lastEffect:null,stores:null},K.updateQueue=t,t.lastEffect=e.next=e):(n=t.lastEffect,n===null?t.lastEffect=e.next=e:(r=n.next,n.next=e,e.next=r,t.lastEffect=e)),e}function kc(){return Ae().memoizedState}function Jr(e,t,n,r){var i=qe();K.flags|=e,i.memoizedState=pr(1|t,n,void 0,r===void 0?null:r)}function Mi(e,t,n,r){var i=Ae();r=r===void 0?null:r;var o=void 0;if(G!==null){var s=G.memoizedState;if(o=s.destroy,r!==null&&nl(r,s.deps)){i.memoizedState=pr(t,n,o,r);return}}K.flags|=e,i.memoizedState=pr(1|t,n,o,r)}function da(e,t){return Jr(8390656,8,e,t)}function ol(e,t){return Mi(2048,8,e,t)}function Sc(e,t){return Mi(4,2,e,t)}function Nc(e,t){return Mi(4,4,e,t)}function jc(e,t){if(typeof t=="function")return e=e(),t(e),function(){t(null)};if(t!=null)return e=e(),t.current=e,function(){t.current=null}}function bc(e,t,n){return n=n!=null?n.concat([e]):null,Mi(4,4,jc.bind(null,t,e),n)}function sl(){}function Ec(e,t){var n=Ae();t=t===void 0?null:t;var r=n.memoizedState;return r!==null&&t!==null&&nl(t,r[1])?r[0]:(n.memoizedState=[e,t],e)}function Cc(e,t){var n=Ae();t=t===void 0?null:t;var r=n.memoizedState;return r!==null&&t!==null&&nl(t,r[1])?r[0]:(e=e(),n.memoizedState=[e,t],e)}function _c(e,t,n){return Ht&21?(Ve(n,t)||(n=Lu(),K.lanes|=n,Vt|=n,e.baseState=!0),t):(e.baseState&&(e.baseState=!1,ye=!0),e.memoizedState=n)}function Pp(e,t){var n=D;D=n!==0&&4>n?n:4,e(!0);var r=vo.transition;vo.transition={};try{e(!1),t()}finally{D=n,vo.transition=r}}function zc(){return Ae().memoizedState}function Tp(e,t,n){var r=jt(e);if(n={lane:r,action:n,hasEagerState:!1,eagerState:null,next:null},Pc(e))Tc(t,n);else if(n=dc(e,t,n,r),n!==null){var i=de();He(n,e,r,i),Rc(n,t,r)}}function Rp(e,t,n){var r=jt(e),i={lane:r,action:n,hasEagerState:!1,eagerState:null,next:null};if(Pc(e))Tc(t,i);else{var o=e.alternate;if(e.lanes===0&&(o===null||o.lanes===0)&&(o=t.lastRenderedReducer,o!==null))try{var s=t.lastRenderedState,l=o(s,n);if(i.hasEagerState=!0,i.eagerState=l,Ve(l,s)){var u=t.interleaved;u===null?(i.next=i,Js(t)):(i.next=u.next,u.next=i),t.interleaved=i;return}}catch{}finally{}n=dc(e,t,i,r),n!==null&&(i=de(),He(n,e,r,i),Rc(n,t,r))}}function Pc(e){var t=e.alternate;return e===K||t!==null&&t===K}function Tc(e,t){Yn=Si=!0;var n=e.pending;n===null?t.next=t:(t.next=n.next,n.next=t),e.pending=t}function Rc(e,t,n){if(n&4194240){var r=t.lanes;r&=e.pendingLanes,n|=r,t.lanes=n,Ms(e,n)}}var Ni={readContext:Fe,useCallback:oe,useContext:oe,useEffect:oe,useImperativeHandle:oe,useInsertionEffect:oe,useLayoutEffect:oe,useMemo:oe,useReducer:oe,useRef:oe,useState:oe,useDebugValue:oe,useDeferredValue:oe,useTransition:oe,useMutableSource:oe,useSyncExternalStore:oe,useId:oe,unstable_isNewReconciler:!1},Lp={readContext:Fe,useCallback:function(e,t){return qe().memoizedState=[e,t===void 0?null:t],e},useContext:Fe,useEffect:da,useImperativeHandle:function(e,t,n){return n=n!=null?n.concat([e]):null,Jr(4194308,4,jc.bind(null,t,e),n)},useLayoutEffect:function(e,t){return Jr(4194308,4,e,t)},useInsertionEffect:function(e,t){return Jr(4,2,e,t)},useMemo:function(e,t){var n=qe();return t=t===void 0?null:t,e=e(),n.memoizedState=[e,t],e},useReducer:function(e,t,n){var r=qe();return t=n!==void 0?n(t):t,r.memoizedState=r.baseState=t,e={pending:null,interleaved:null,lanes:0,dispatch:null,lastRenderedReducer:e,lastRenderedState:t},r.queue=e,e=e.dispatch=Tp.bind(null,K,e),[r.memoizedState,e]},useRef:function(e){var t=qe();return e={current:e},t.memoizedState=e},useState:ca,useDebugValue:sl,useDeferredValue:function(e){return qe().memoizedState=e},useTransition:function(){var e=ca(!1),t=e[0];return e=Pp.bind(null,e[1]),qe().memoizedState=e,[t,e]},useMutableSource:function(){},useSyncExternalStore:function(e,t,n){var r=K,i=qe();if(W){if(n===void 0)throw Error(N(407));n=n()}else{if(n=t(),te===null)throw Error(N(349));Ht&30||gc(r,t,n)}i.memoizedState=n;var o={value:n,getSnapshot:t};return i.queue=o,da(vc.bind(null,r,o,e),[e]),r.flags|=2048,pr(9,yc.bind(null,r,o,n,t),void 0,null),n},useId:function(){var e=qe(),t=te.identifierPrefix;if(W){var n=rt,r=nt;n=(r&~(1<<32-Be(r)-1)).toString(32)+n,t=":"+t+"R"+n,n=dr++,0<n&&(t+="H"+n.toString(32)),t+=":"}else n=zp++,t=":"+t+"r"+n.toString(32)+":";return e.memoizedState=t},unstable_isNewReconciler:!1},Op={readContext:Fe,useCallback:Ec,useContext:Fe,useEffect:ol,useImperativeHandle:bc,useInsertionEffect:Sc,useLayoutEffect:Nc,useMemo:Cc,useReducer:xo,useRef:kc,useState:function(){return xo(fr)},useDebugValue:sl,useDeferredValue:function(e){var t=Ae();return _c(t,G.memoizedState,e)},useTransition:function(){var e=xo(fr)[0],t=Ae().memoizedState;return[e,t]},useMutableSource:mc,useSyncExternalStore:hc,useId:zc,unstable_isNewReconciler:!1},Fp={readContext:Fe,useCallback:Ec,useContext:Fe,useEffect:ol,useImperativeHandle:bc,useInsertionEffect:Sc,useLayoutEffect:Nc,useMemo:Cc,useReducer:wo,useRef:kc,useState:function(){return wo(fr)},useDebugValue:sl,useDeferredValue:function(e){var t=Ae();return G===null?t.memoizedState=e:_c(t,G.memoizedState,e)},useTransition:function(){var e=wo(fr)[0],t=Ae().memoizedState;return[e,t]},useMutableSource:mc,useSyncExternalStore:hc,useId:zc,unstable_isNewReconciler:!1};function Ue(e,t){if(e&&e.defaultProps){t=Y({},t),e=e.defaultProps;for(var n in e)t[n]===void 0&&(t[n]=e[n]);return t}return t}function is(e,t,n,r){t=e.memoizedState,n=n(r,t),n=n==null?t:Y({},t,n),e.memoizedState=n,e.lanes===0&&(e.updateQueue.baseState=n)}var Di={isMounted:function(e){return(e=e._reactInternals)?Yt(e)===e:!1},enqueueSetState:function(e,t,n){e=e._reactInternals;var r=de(),i=jt(e),o=it(r,i);o.payload=t,n!=null&&(o.callback=n),t=St(e,o,i),t!==null&&(He(t,e,i,r),Xr(t,e,i))},enqueueReplaceState:function(e,t,n){e=e._reactInternals;var r=de(),i=jt(e),o=it(r,i);o.tag=1,o.payload=t,n!=null&&(o.callback=n),t=St(e,o,i),t!==null&&(He(t,e,i,r),Xr(t,e,i))},enqueueForceUpdate:function(e,t){e=e._reactInternals;var n=de(),r=jt(e),i=it(n,r);i.tag=2,t!=null&&(i.callback=t),t=St(e,i,r),t!==null&&(He(t,e,r,n),Xr(t,e,r))}};function fa(e,t,n,r,i,o,s){return e=e.stateNode,typeof e.shouldComponentUpdate=="function"?e.shouldComponentUpdate(r,o,s):t.prototype&&t.prototype.isPureReactComponent?!or(n,r)||!or(i,o):!0}function Lc(e,t,n){var r=!1,i=Ct,o=t.contextType;return typeof o=="object"&&o!==null?o=Fe(o):(i=xe(t)?$t:ue.current,r=t.contextTypes,o=(r=r!=null)?vn(e,i):Ct),t=new t(n,o),e.memoizedState=t.state!==null&&t.state!==void 0?t.state:null,t.updater=Di,e.stateNode=t,t._reactInternals=e,r&&(e=e.stateNode,e.__reactInternalMemoizedUnmaskedChildContext=i,e.__reactInternalMemoizedMaskedChildContext=o),t}function pa(e,t,n,r){e=t.state,typeof t.componentWillReceiveProps=="function"&&t.componentWillReceiveProps(n,r),typeof t.UNSAFE_componentWillReceiveProps=="function"&&t.UNSAFE_componentWillReceiveProps(n,r),t.state!==e&&Di.enqueueReplaceState(t,t.state,null)}function os(e,t,n,r){var i=e.stateNode;i.props=n,i.state=e.memoizedState,i.refs={},Gs(e);var o=t.contextType;typeof o=="object"&&o!==null?i.context=Fe(o):(o=xe(t)?$t:ue.current,i.context=vn(e,o)),i.state=e.memoizedState,o=t.getDerivedStateFromProps,typeof o=="function"&&(is(e,t,o,n),i.state=e.memoizedState),typeof t.getDerivedStateFromProps=="function"||typeof i.getSnapshotBeforeUpdate=="function"||typeof i.UNSAFE_componentWillMount!="function"&&typeof i.componentWillMount!="function"||(t=i.state,typeof i.componentWillMount=="function"&&i.componentWillMount(),typeof i.UNSAFE_componentWillMount=="function"&&i.UNSAFE_componentWillMount(),t!==i.state&&Di.enqueueReplaceState(i,i.state,null),wi(e,n,i,r),i.state=e.memoizedState),typeof i.componentDidMount=="function"&&(e.flags|=4194308)}function Sn(e,t){try{var n="",r=t;do n+=uf(r),r=r.return;while(r);var i=n}catch(o){i=`
Error generating stack: `+o.message+`
`+o.stack}return{value:e,source:t,stack:i,digest:null}}function ko(e,t,n){return{value:e,source:null,stack:n??null,digest:t??null}}function ss(e,t){try{console.error(t.value)}catch(n){setTimeout(function(){throw n})}}var Ap=typeof WeakMap=="function"?WeakMap:Map;function Oc(e,t,n){n=it(-1,n),n.tag=3,n.payload={element:null};var r=t.value;return n.callback=function(){bi||(bi=!0,gs=r),ss(e,t)},n}function Fc(e,t,n){n=it(-1,n),n.tag=3;var r=e.type.getDerivedStateFromError;if(typeof r=="function"){var i=t.value;n.payload=function(){return r(i)},n.callback=function(){ss(e,t)}}var o=e.stateNode;return o!==null&&typeof o.componentDidCatch=="function"&&(n.callback=function(){ss(e,t),typeof r!="function"&&(Nt===null?Nt=new Set([this]):Nt.add(this));var s=t.stack;this.componentDidCatch(t.value,{componentStack:s!==null?s:""})}),n}function ma(e,t,n){var r=e.pingCache;if(r===null){r=e.pingCache=new Ap;var i=new Set;r.set(t,i)}else i=r.get(t),i===void 0&&(i=new Set,r.set(t,i));i.has(n)||(i.add(n),e=qp.bind(null,e,t,n),t.then(e,e))}function ha(e){do{var t;if((t=e.tag===13)&&(t=e.memoizedState,t=t!==null?t.dehydrated!==null:!0),t)return e;e=e.return}while(e!==null);return null}function ga(e,t,n,r,i){return e.mode&1?(e.flags|=65536,e.lanes=i,e):(e===t?e.flags|=65536:(e.flags|=128,n.flags|=131072,n.flags&=-52805,n.tag===1&&(n.alternate===null?n.tag=17:(t=it(-1,1),t.tag=2,St(n,t,1))),n.lanes|=1),e)}var Mp=ut.ReactCurrentOwner,ye=!1;function ce(e,t,n,r){t.child=e===null?cc(t,null,n,r):wn(t,e.child,n,r)}function ya(e,t,n,r,i){n=n.render;var o=t.ref;return hn(t,i),r=rl(e,t,n,r,o,i),n=il(),e!==null&&!ye?(t.updateQueue=e.updateQueue,t.flags&=-2053,e.lanes&=~i,at(e,t,i)):(W&&n&&Ws(t),t.flags|=1,ce(e,t,r,i),t.child)}function va(e,t,n,r,i){if(e===null){var o=n.type;return typeof o=="function"&&!ml(o)&&o.defaultProps===void 0&&n.compare===null&&n.defaultProps===void 0?(t.tag=15,t.type=o,Ac(e,t,o,r,i)):(e=ti(n.type,null,r,t,t.mode,i),e.ref=t.ref,e.return=t,t.child=e)}if(o=e.child,!(e.lanes&i)){var s=o.memoizedProps;if(n=n.compare,n=n!==null?n:or,n(s,r)&&e.ref===t.ref)return at(e,t,i)}return t.flags|=1,e=bt(o,r),e.ref=t.ref,e.return=t,t.child=e}function Ac(e,t,n,r,i){if(e!==null){var o=e.memoizedProps;if(or(o,r)&&e.ref===t.ref)if(ye=!1,t.pendingProps=r=o,(e.lanes&i)!==0)e.flags&131072&&(ye=!0);else return t.lanes=e.lanes,at(e,t,i)}return ls(e,t,n,r,i)}function Mc(e,t,n){var r=t.pendingProps,i=r.children,o=e!==null?e.memoizedState:null;if(r.mode==="hidden")if(!(t.mode&1))t.memoizedState={baseLanes:0,cachePool:null,transitions:null},$(cn,Ne),Ne|=n;else{if(!(n&1073741824))return e=o!==null?o.baseLanes|n:n,t.lanes=t.childLanes=1073741824,t.memoizedState={baseLanes:e,cachePool:null,transitions:null},t.updateQueue=null,$(cn,Ne),Ne|=e,null;t.memoizedState={baseLanes:0,cachePool:null,transitions:null},r=o!==null?o.baseLanes:n,$(cn,Ne),Ne|=r}else o!==null?(r=o.baseLanes|n,t.memoizedState=null):r=n,$(cn,Ne),Ne|=r;return ce(e,t,i,n),t.child}function Dc(e,t){var n=t.ref;(e===null&&n!==null||e!==null&&e.ref!==n)&&(t.flags|=512,t.flags|=2097152)}function ls(e,t,n,r,i){var o=xe(n)?$t:ue.current;return o=vn(t,o),hn(t,i),n=rl(e,t,n,r,o,i),r=il(),e!==null&&!ye?(t.updateQueue=e.updateQueue,t.flags&=-2053,e.lanes&=~i,at(e,t,i)):(W&&r&&Ws(t),t.flags|=1,ce(e,t,n,i),t.child)}function xa(e,t,n,r,i){if(xe(n)){var o=!0;hi(t)}else o=!1;if(hn(t,i),t.stateNode===null)Gr(e,t),Lc(t,n,r),os(t,n,r,i),r=!0;else if(e===null){var s=t.stateNode,l=t.memoizedProps;s.props=l;var u=s.context,c=n.contextType;typeof c=="object"&&c!==null?c=Fe(c):(c=xe(n)?$t:ue.current,c=vn(t,c));var p=n.getDerivedStateFromProps,g=typeof p=="function"||typeof s.getSnapshotBeforeUpdate=="function";g||typeof s.UNSAFE_componentWillReceiveProps!="function"&&typeof s.componentWillReceiveProps!="function"||(l!==r||u!==c)&&pa(t,s,r,c),pt=!1;var y=t.memoizedState;s.state=y,wi(t,r,s,i),u=t.memoizedState,l!==r||y!==u||ve.current||pt?(typeof p=="function"&&(is(t,n,p,r),u=t.memoizedState),(l=pt||fa(t,n,l,r,y,u,c))?(g||typeof s.UNSAFE_componentWillMount!="function"&&typeof s.componentWillMount!="function"||(typeof s.componentWillMount=="function"&&s.componentWillMount(),typeof s.UNSAFE_componentWillMount=="function"&&s.UNSAFE_componentWillMount()),typeof s.componentDidMount=="function"&&(t.flags|=4194308)):(typeof s.componentDidMount=="function"&&(t.flags|=4194308),t.memoizedProps=r,t.memoizedState=u),s.props=r,s.state=u,s.context=c,r=l):(typeof s.componentDidMount=="function"&&(t.flags|=4194308),r=!1)}else{s=t.stateNode,fc(e,t),l=t.memoizedProps,c=t.type===t.elementType?l:Ue(t.type,l),s.props=c,g=t.pendingProps,y=s.context,u=n.contextType,typeof u=="object"&&u!==null?u=Fe(u):(u=xe(n)?$t:ue.current,u=vn(t,u));var S=n.getDerivedStateFromProps;(p=typeof S=="function"||typeof s.getSnapshotBeforeUpdate=="function")||typeof s.UNSAFE_componentWillReceiveProps!="function"&&typeof s.componentWillReceiveProps!="function"||(l!==g||y!==u)&&pa(t,s,r,u),pt=!1,y=t.memoizedState,s.state=y,wi(t,r,s,i);var m=t.memoizedState;l!==g||y!==m||ve.current||pt?(typeof S=="function"&&(is(t,n,S,r),m=t.memoizedState),(c=pt||fa(t,n,c,r,y,m,u)||!1)?(p||typeof s.UNSAFE_componentWillUpdate!="function"&&typeof s.componentWillUpdate!="function"||(typeof s.componentWillUpdate=="function"&&s.componentWillUpdate(r,m,u),typeof s.UNSAFE_componentWillUpdate=="function"&&s.UNSAFE_componentWillUpdate(r,m,u)),typeof s.componentDidUpdate=="function"&&(t.flags|=4),typeof s.getSnapshotBeforeUpdate=="function"&&(t.flags|=1024)):(typeof s.componentDidUpdate!="function"||l===e.memoizedProps&&y===e.memoizedState||(t.flags|=4),typeof s.getSnapshotBeforeUpdate!="function"||l===e.memoizedProps&&y===e.memoizedState||(t.flags|=1024),t.memoizedProps=r,t.memoizedState=m),s.props=r,s.state=m,s.context=u,r=c):(typeof s.componentDidUpdate!="function"||l===e.memoizedProps&&y===e.memoizedState||(t.flags|=4),typeof s.getSnapshotBeforeUpdate!="function"||l===e.memoizedProps&&y===e.memoizedState||(t.flags|=1024),r=!1)}return as(e,t,n,r,o,i)}function as(e,t,n,r,i,o){Dc(e,t);var s=(t.flags&128)!==0;if(!r&&!s)return i&&ia(t,n,!1),at(e,t,o);r=t.stateNode,Mp.current=t;var l=s&&typeof n.getDerivedStateFromError!="function"?null:r.render();return t.flags|=1,e!==null&&s?(t.child=wn(t,e.child,null,o),t.child=wn(t,null,l,o)):ce(e,t,l,o),t.memoizedState=r.state,i&&ia(t,n,!0),t.child}function Uc(e){var t=e.stateNode;t.pendingContext?ra(e,t.pendingContext,t.pendingContext!==t.context):t.context&&ra(e,t.context,!1),Zs(e,t.containerInfo)}function wa(e,t,n,r,i){return xn(),Ks(i),t.flags|=256,ce(e,t,n,r),t.child}var us={dehydrated:null,treeContext:null,retryLane:0};function cs(e){return{baseLanes:e,cachePool:null,transitions:null}}function Ic(e,t,n){var r=t.pendingProps,i=Q.current,o=!1,s=(t.flags&128)!==0,l;if((l=s)||(l=e!==null&&e.memoizedState===null?!1:(i&2)!==0),l?(o=!0,t.flags&=-129):(e===null||e.memoizedState!==null)&&(i|=1),$(Q,i&1),e===null)return ns(t),e=t.memoizedState,e!==null&&(e=e.dehydrated,e!==null)?(t.mode&1?e.data==="$!"?t.lanes=8:t.lanes=1073741824:t.lanes=1,null):(s=r.children,e=r.fallback,o?(r=t.mode,o=t.child,s={mode:"hidden",children:s},!(r&1)&&o!==null?(o.childLanes=0,o.pendingProps=s):o=$i(s,r,0,null),e=Ut(e,r,n,null),o.return=t,e.return=t,o.sibling=e,t.child=o,t.child.memoizedState=cs(n),t.memoizedState=us,e):ll(t,s));if(i=e.memoizedState,i!==null&&(l=i.dehydrated,l!==null))return Dp(e,t,s,r,l,i,n);if(o){o=r.fallback,s=t.mode,i=e.child,l=i.sibling;var u={mode:"hidden",children:r.children};return!(s&1)&&t.child!==i?(r=t.child,r.childLanes=0,r.pendingProps=u,t.deletions=null):(r=bt(i,u),r.subtreeFlags=i.subtreeFlags&14680064),l!==null?o=bt(l,o):(o=Ut(o,s,n,null),o.flags|=2),o.return=t,r.return=t,r.sibling=o,t.child=r,r=o,o=t.child,s=e.child.memoizedState,s=s===null?cs(n):{baseLanes:s.baseLanes|n,cachePool:null,transitions:s.transitions},o.memoizedState=s,o.childLanes=e.childLanes&~n,t.memoizedState=us,r}return o=e.child,e=o.sibling,r=bt(o,{mode:"visible",children:r.children}),!(t.mode&1)&&(r.lanes=n),r.return=t,r.sibling=null,e!==null&&(n=t.deletions,n===null?(t.deletions=[e],t.flags|=16):n.push(e)),t.child=r,t.memoizedState=null,r}function ll(e,t){return t=$i({mode:"visible",children:t},e.mode,0,null),t.return=e,e.child=t}function Ur(e,t,n,r){return r!==null&&Ks(r),wn(t,e.child,null,n),e=ll(t,t.pendingProps.children),e.flags|=2,t.memoizedState=null,e}function Dp(e,t,n,r,i,o,s){if(n)return t.flags&256?(t.flags&=-257,r=ko(Error(N(422))),Ur(e,t,s,r)):t.memoizedState!==null?(t.child=e.child,t.flags|=128,null):(o=r.fallback,i=t.mode,r=$i({mode:"visible",children:r.children},i,0,null),o=Ut(o,i,s,null),o.flags|=2,r.return=t,o.return=t,r.sibling=o,t.child=r,t.mode&1&&wn(t,e.child,null,s),t.child.memoizedState=cs(s),t.memoizedState=us,o);if(!(t.mode&1))return Ur(e,t,s,null);if(i.data==="$!"){if(r=i.nextSibling&&i.nextSibling.dataset,r)var l=r.dgst;return r=l,o=Error(N(419)),r=ko(o,r,void 0),Ur(e,t,s,r)}if(l=(s&e.childLanes)!==0,ye||l){if(r=te,r!==null){switch(s&-s){case 4:i=2;break;case 16:i=8;break;case 64:case 128:case 256:case 512:case 1024:case 2048:case 4096:case 8192:case 16384:case 32768:case 65536:case 131072:case 262144:case 524288:case 1048576:case 2097152:case 4194304:case 8388608:case 16777216:case 33554432:case 67108864:i=32;break;case 536870912:i=268435456;break;default:i=0}i=i&(r.suspendedLanes|s)?0:i,i!==0&&i!==o.retryLane&&(o.retryLane=i,lt(e,i),He(r,e,i,-1))}return pl(),r=ko(Error(N(421))),Ur(e,t,s,r)}return i.data==="$?"?(t.flags|=128,t.child=e.child,t=Jp.bind(null,e),i._reactRetry=t,null):(e=o.treeContext,je=kt(i.nextSibling),be=t,W=!0,$e=null,e!==null&&(Pe[Te++]=nt,Pe[Te++]=rt,Pe[Te++]=Bt,nt=e.id,rt=e.overflow,Bt=t),t=ll(t,r.children),t.flags|=4096,t)}function ka(e,t,n){e.lanes|=t;var r=e.alternate;r!==null&&(r.lanes|=t),rs(e.return,t,n)}function So(e,t,n,r,i){var o=e.memoizedState;o===null?e.memoizedState={isBackwards:t,rendering:null,renderingStartTime:0,last:r,tail:n,tailMode:i}:(o.isBackwards=t,o.rendering=null,o.renderingStartTime=0,o.last=r,o.tail=n,o.tailMode=i)}function $c(e,t,n){var r=t.pendingProps,i=r.revealOrder,o=r.tail;if(ce(e,t,r.children,n),r=Q.current,r&2)r=r&1|2,t.flags|=128;else{if(e!==null&&e.flags&128)e:for(e=t.child;e!==null;){if(e.tag===13)e.memoizedState!==null&&ka(e,n,t);else if(e.tag===19)ka(e,n,t);else if(e.child!==null){e.child.return=e,e=e.child;continue}if(e===t)break e;for(;e.sibling===null;){if(e.return===null||e.return===t)break e;e=e.return}e.sibling.return=e.return,e=e.sibling}r&=1}if($(Q,r),!(t.mode&1))t.memoizedState=null;else switch(i){case"forwards":for(n=t.child,i=null;n!==null;)e=n.alternate,e!==null&&ki(e)===null&&(i=n),n=n.sibling;n=i,n===null?(i=t.child,t.child=null):(i=n.sibling,n.sibling=null),So(t,!1,i,n,o);break;case"backwards":for(n=null,i=t.child,t.child=null;i!==null;){if(e=i.alternate,e!==null&&ki(e)===null){t.child=i;break}e=i.sibling,i.sibling=n,n=i,i=e}So(t,!0,n,null,o);break;case"together":So(t,!1,null,null,void 0);break;default:t.memoizedState=null}return t.child}function Gr(e,t){!(t.mode&1)&&e!==null&&(e.alternate=null,t.alternate=null,t.flags|=2)}function at(e,t,n){if(e!==null&&(t.dependencies=e.dependencies),Vt|=t.lanes,!(n&t.childLanes))return null;if(e!==null&&t.child!==e.child)throw Error(N(153));if(t.child!==null){for(e=t.child,n=bt(e,e.pendingProps),t.child=n,n.return=t;e.sibling!==null;)e=e.sibling,n=n.sibling=bt(e,e.pendingProps),n.return=t;n.sibling=null}return t.child}function Up(e,t,n){switch(t.tag){case 3:Uc(t),xn();break;case 5:pc(t);break;case 1:xe(t.type)&&hi(t);break;case 4:Zs(t,t.stateNode.containerInfo);break;case 10:var r=t.type._context,i=t.memoizedProps.value;$(vi,r._currentValue),r._currentValue=i;break;case 13:if(r=t.memoizedState,r!==null)return r.dehydrated!==null?($(Q,Q.current&1),t.flags|=128,null):n&t.child.childLanes?Ic(e,t,n):($(Q,Q.current&1),e=at(e,t,n),e!==null?e.sibling:null);$(Q,Q.current&1);break;case 19:if(r=(n&t.childLanes)!==0,e.flags&128){if(r)return $c(e,t,n);t.flags|=128}if(i=t.memoizedState,i!==null&&(i.rendering=null,i.tail=null,i.lastEffect=null),$(Q,Q.current),r)break;return null;case 22:case 23:return t.lanes=0,Mc(e,t,n)}return at(e,t,n)}var Bc,ds,Hc,Vc;Bc=function(e,t){for(var n=t.child;n!==null;){if(n.tag===5||n.tag===6)e.appendChild(n.stateNode);else if(n.tag!==4&&n.child!==null){n.child.return=n,n=n.child;continue}if(n===t)break;for(;n.sibling===null;){if(n.return===null||n.return===t)return;n=n.return}n.sibling.return=n.return,n=n.sibling}};ds=function(){};Hc=function(e,t,n,r){var i=e.memoizedProps;if(i!==r){e=t.stateNode,At(Ze.current);var o=null;switch(n){case"input":i=Lo(e,i),r=Lo(e,r),o=[];break;case"select":i=Y({},i,{value:void 0}),r=Y({},r,{value:void 0}),o=[];break;case"textarea":i=Ao(e,i),r=Ao(e,r),o=[];break;default:typeof i.onClick!="function"&&typeof r.onClick=="function"&&(e.onclick=pi)}Do(n,r);var s;n=null;for(c in i)if(!r.hasOwnProperty(c)&&i.hasOwnProperty(c)&&i[c]!=null)if(c==="style"){var l=i[c];for(s in l)l.hasOwnProperty(s)&&(n||(n={}),n[s]="")}else c!=="dangerouslySetInnerHTML"&&c!=="children"&&c!=="suppressContentEditableWarning"&&c!=="suppressHydrationWarning"&&c!=="autoFocus"&&(Gn.hasOwnProperty(c)?o||(o=[]):(o=o||[]).push(c,null));for(c in r){var u=r[c];if(l=i!=null?i[c]:void 0,r.hasOwnProperty(c)&&u!==l&&(u!=null||l!=null))if(c==="style")if(l){for(s in l)!l.hasOwnProperty(s)||u&&u.hasOwnProperty(s)||(n||(n={}),n[s]="");for(s in u)u.hasOwnProperty(s)&&l[s]!==u[s]&&(n||(n={}),n[s]=u[s])}else n||(o||(o=[]),o.push(c,n)),n=u;else c==="dangerouslySetInnerHTML"?(u=u?u.__html:void 0,l=l?l.__html:void 0,u!=null&&l!==u&&(o=o||[]).push(c,u)):c==="children"?typeof u!="string"&&typeof u!="number"||(o=o||[]).push(c,""+u):c!=="suppressContentEditableWarning"&&c!=="suppressHydrationWarning"&&(Gn.hasOwnProperty(c)?(u!=null&&c==="onScroll"&&H("scroll",e),o||l===u||(o=[])):(o=o||[]).push(c,u))}n&&(o=o||[]).push("style",n);var c=o;(t.updateQueue=c)&&(t.flags|=4)}};Vc=function(e,t,n,r){n!==r&&(t.flags|=4)};function An(e,t){if(!W)switch(e.tailMode){case"hidden":t=e.tail;for(var n=null;t!==null;)t.alternate!==null&&(n=t),t=t.sibling;n===null?e.tail=null:n.sibling=null;break;case"collapsed":n=e.tail;for(var r=null;n!==null;)n.alternate!==null&&(r=n),n=n.sibling;r===null?t||e.tail===null?e.tail=null:e.tail.sibling=null:r.sibling=null}}function se(e){var t=e.alternate!==null&&e.alternate.child===e.child,n=0,r=0;if(t)for(var i=e.child;i!==null;)n|=i.lanes|i.childLanes,r|=i.subtreeFlags&14680064,r|=i.flags&14680064,i.return=e,i=i.sibling;else for(i=e.child;i!==null;)n|=i.lanes|i.childLanes,r|=i.subtreeFlags,r|=i.flags,i.return=e,i=i.sibling;return e.subtreeFlags|=r,e.childLanes=n,t}function Ip(e,t,n){var r=t.pendingProps;switch(Qs(t),t.tag){case 2:case 16:case 15:case 0:case 11:case 7:case 8:case 12:case 9:case 14:return se(t),null;case 1:return xe(t.type)&&mi(),se(t),null;case 3:return r=t.stateNode,kn(),V(ve),V(ue),tl(),r.pendingContext&&(r.context=r.pendingContext,r.pendingContext=null),(e===null||e.child===null)&&(Mr(t)?t.flags|=4:e===null||e.memoizedState.isDehydrated&&!(t.flags&256)||(t.flags|=1024,$e!==null&&(xs($e),$e=null))),ds(e,t),se(t),null;case 5:el(t);var i=At(cr.current);if(n=t.type,e!==null&&t.stateNode!=null)Hc(e,t,n,r,i),e.ref!==t.ref&&(t.flags|=512,t.flags|=2097152);else{if(!r){if(t.stateNode===null)throw Error(N(166));return se(t),null}if(e=At(Ze.current),Mr(t)){r=t.stateNode,n=t.type;var o=t.memoizedProps;switch(r[Je]=t,r[ar]=o,e=(t.mode&1)!==0,n){case"dialog":H("cancel",r),H("close",r);break;case"iframe":case"object":case"embed":H("load",r);break;case"video":case"audio":for(i=0;i<Bn.length;i++)H(Bn[i],r);break;case"source":H("error",r);break;case"img":case"image":case"link":H("error",r),H("load",r);break;case"details":H("toggle",r);break;case"input":Pl(r,o),H("invalid",r);break;case"select":r._wrapperState={wasMultiple:!!o.multiple},H("invalid",r);break;case"textarea":Rl(r,o),H("invalid",r)}Do(n,o),i=null;for(var s in o)if(o.hasOwnProperty(s)){var l=o[s];s==="children"?typeof l=="string"?r.textContent!==l&&(o.suppressHydrationWarning!==!0&&Ar(r.textContent,l,e),i=["children",l]):typeof l=="number"&&r.textContent!==""+l&&(o.suppressHydrationWarning!==!0&&Ar(r.textContent,l,e),i=["children",""+l]):Gn.hasOwnProperty(s)&&l!=null&&s==="onScroll"&&H("scroll",r)}switch(n){case"input":_r(r),Tl(r,o,!0);break;case"textarea":_r(r),Ll(r);break;case"select":case"option":break;default:typeof o.onClick=="function"&&(r.onclick=pi)}r=i,t.updateQueue=r,r!==null&&(t.flags|=4)}else{s=i.nodeType===9?i:i.ownerDocument,e==="http://www.w3.org/1999/xhtml"&&(e=vu(n)),e==="http://www.w3.org/1999/xhtml"?n==="script"?(e=s.createElement("div"),e.innerHTML="<script><\/script>",e=e.removeChild(e.firstChild)):typeof r.is=="string"?e=s.createElement(n,{is:r.is}):(e=s.createElement(n),n==="select"&&(s=e,r.multiple?s.multiple=!0:r.size&&(s.size=r.size))):e=s.createElementNS(e,n),e[Je]=t,e[ar]=r,Bc(e,t,!1,!1),t.stateNode=e;e:{switch(s=Uo(n,r),n){case"dialog":H("cancel",e),H("close",e),i=r;break;case"iframe":case"object":case"embed":H("load",e),i=r;break;case"video":case"audio":for(i=0;i<Bn.length;i++)H(Bn[i],e);i=r;break;case"source":H("error",e),i=r;break;case"img":case"image":case"link":H("error",e),H("load",e),i=r;break;case"details":H("toggle",e),i=r;break;case"input":Pl(e,r),i=Lo(e,r),H("invalid",e);break;case"option":i=r;break;case"select":e._wrapperState={wasMultiple:!!r.multiple},i=Y({},r,{value:void 0}),H("invalid",e);break;case"textarea":Rl(e,r),i=Ao(e,r),H("invalid",e);break;default:i=r}Do(n,i),l=i;for(o in l)if(l.hasOwnProperty(o)){var u=l[o];o==="style"?ku(e,u):o==="dangerouslySetInnerHTML"?(u=u?u.__html:void 0,u!=null&&xu(e,u)):o==="children"?typeof u=="string"?(n!=="textarea"||u!=="")&&Zn(e,u):typeof u=="number"&&Zn(e,""+u):o!=="suppressContentEditableWarning"&&o!=="suppressHydrationWarning"&&o!=="autoFocus"&&(Gn.hasOwnProperty(o)?u!=null&&o==="onScroll"&&H("scroll",e):u!=null&&Ts(e,o,u,s))}switch(n){case"input":_r(e),Tl(e,r,!1);break;case"textarea":_r(e),Ll(e);break;case"option":r.value!=null&&e.setAttribute("value",""+Et(r.value));break;case"select":e.multiple=!!r.multiple,o=r.value,o!=null?dn(e,!!r.multiple,o,!1):r.defaultValue!=null&&dn(e,!!r.multiple,r.defaultValue,!0);break;default:typeof i.onClick=="function"&&(e.onclick=pi)}switch(n){case"button":case"input":case"select":case"textarea":r=!!r.autoFocus;break e;case"img":r=!0;break e;default:r=!1}}r&&(t.flags|=4)}t.ref!==null&&(t.flags|=512,t.flags|=2097152)}return se(t),null;case 6:if(e&&t.stateNode!=null)Vc(e,t,e.memoizedProps,r);else{if(typeof r!="string"&&t.stateNode===null)throw Error(N(166));if(n=At(cr.current),At(Ze.current),Mr(t)){if(r=t.stateNode,n=t.memoizedProps,r[Je]=t,(o=r.nodeValue!==n)&&(e=be,e!==null))switch(e.tag){case 3:Ar(r.nodeValue,n,(e.mode&1)!==0);break;case 5:e.memoizedProps.suppressHydrationWarning!==!0&&Ar(r.nodeValue,n,(e.mode&1)!==0)}o&&(t.flags|=4)}else r=(n.nodeType===9?n:n.ownerDocument).createTextNode(r),r[Je]=t,t.stateNode=r}return se(t),null;case 13:if(V(Q),r=t.memoizedState,e===null||e.memoizedState!==null&&e.memoizedState.dehydrated!==null){if(W&&je!==null&&t.mode&1&&!(t.flags&128))ac(),xn(),t.flags|=98560,o=!1;else if(o=Mr(t),r!==null&&r.dehydrated!==null){if(e===null){if(!o)throw Error(N(318));if(o=t.memoizedState,o=o!==null?o.dehydrated:null,!o)throw Error(N(317));o[Je]=t}else xn(),!(t.flags&128)&&(t.memoizedState=null),t.flags|=4;se(t),o=!1}else $e!==null&&(xs($e),$e=null),o=!0;if(!o)return t.flags&65536?t:null}return t.flags&128?(t.lanes=n,t):(r=r!==null,r!==(e!==null&&e.memoizedState!==null)&&r&&(t.child.flags|=8192,t.mode&1&&(e===null||Q.current&1?Z===0&&(Z=3):pl())),t.updateQueue!==null&&(t.flags|=4),se(t),null);case 4:return kn(),ds(e,t),e===null&&sr(t.stateNode.containerInfo),se(t),null;case 10:return qs(t.type._context),se(t),null;case 17:return xe(t.type)&&mi(),se(t),null;case 19:if(V(Q),o=t.memoizedState,o===null)return se(t),null;if(r=(t.flags&128)!==0,s=o.rendering,s===null)if(r)An(o,!1);else{if(Z!==0||e!==null&&e.flags&128)for(e=t.child;e!==null;){if(s=ki(e),s!==null){for(t.flags|=128,An(o,!1),r=s.updateQueue,r!==null&&(t.updateQueue=r,t.flags|=4),t.subtreeFlags=0,r=n,n=t.child;n!==null;)o=n,e=r,o.flags&=14680066,s=o.alternate,s===null?(o.childLanes=0,o.lanes=e,o.child=null,o.subtreeFlags=0,o.memoizedProps=null,o.memoizedState=null,o.updateQueue=null,o.dependencies=null,o.stateNode=null):(o.childLanes=s.childLanes,o.lanes=s.lanes,o.child=s.child,o.subtreeFlags=0,o.deletions=null,o.memoizedProps=s.memoizedProps,o.memoizedState=s.memoizedState,o.updateQueue=s.updateQueue,o.type=s.type,e=s.dependencies,o.dependencies=e===null?null:{lanes:e.lanes,firstContext:e.firstContext}),n=n.sibling;return $(Q,Q.current&1|2),t.child}e=e.sibling}o.tail!==null&&q()>Nn&&(t.flags|=128,r=!0,An(o,!1),t.lanes=4194304)}else{if(!r)if(e=ki(s),e!==null){if(t.flags|=128,r=!0,n=e.updateQueue,n!==null&&(t.updateQueue=n,t.flags|=4),An(o,!0),o.tail===null&&o.tailMode==="hidden"&&!s.alternate&&!W)return se(t),null}else 2*q()-o.renderingStartTime>Nn&&n!==1073741824&&(t.flags|=128,r=!0,An(o,!1),t.lanes=4194304);o.isBackwards?(s.sibling=t.child,t.child=s):(n=o.last,n!==null?n.sibling=s:t.child=s,o.last=s)}return o.tail!==null?(t=o.tail,o.rendering=t,o.tail=t.sibling,o.renderingStartTime=q(),t.sibling=null,n=Q.current,$(Q,r?n&1|2:n&1),t):(se(t),null);case 22:case 23:return fl(),r=t.memoizedState!==null,e!==null&&e.memoizedState!==null!==r&&(t.flags|=8192),r&&t.mode&1?Ne&1073741824&&(se(t),t.subtreeFlags&6&&(t.flags|=8192)):se(t),null;case 24:return null;case 25:return null}throw Error(N(156,t.tag))}function $p(e,t){switch(Qs(t),t.tag){case 1:return xe(t.type)&&mi(),e=t.flags,e&65536?(t.flags=e&-65537|128,t):null;case 3:return kn(),V(ve),V(ue),tl(),e=t.flags,e&65536&&!(e&128)?(t.flags=e&-65537|128,t):null;case 5:return el(t),null;case 13:if(V(Q),e=t.memoizedState,e!==null&&e.dehydrated!==null){if(t.alternate===null)throw Error(N(340));xn()}return e=t.flags,e&65536?(t.flags=e&-65537|128,t):null;case 19:return V(Q),null;case 4:return kn(),null;case 10:return qs(t.type._context),null;case 22:case 23:return fl(),null;case 24:return null;default:return null}}var Ir=!1,le=!1,Bp=typeof WeakSet=="function"?WeakSet:Set,C=null;function un(e,t){var n=e.ref;if(n!==null)if(typeof n=="function")try{n(null)}catch(r){X(e,t,r)}else n.current=null}function fs(e,t,n){try{n()}catch(r){X(e,t,r)}}var Sa=!1;function Hp(e,t){if(Xo=ci,e=Xu(),Vs(e)){if("selectionStart"in e)var n={start:e.selectionStart,end:e.selectionEnd};else e:{n=(n=e.ownerDocument)&&n.defaultView||window;var r=n.getSelection&&n.getSelection();if(r&&r.rangeCount!==0){n=r.anchorNode;var i=r.anchorOffset,o=r.focusNode;r=r.focusOffset;try{n.nodeType,o.nodeType}catch{n=null;break e}var s=0,l=-1,u=-1,c=0,p=0,g=e,y=null;t:for(;;){for(var S;g!==n||i!==0&&g.nodeType!==3||(l=s+i),g!==o||r!==0&&g.nodeType!==3||(u=s+r),g.nodeType===3&&(s+=g.nodeValue.length),(S=g.firstChild)!==null;)y=g,g=S;for(;;){if(g===e)break t;if(y===n&&++c===i&&(l=s),y===o&&++p===r&&(u=s),(S=g.nextSibling)!==null)break;g=y,y=g.parentNode}g=S}n=l===-1||u===-1?null:{start:l,end:u}}else n=null}n=n||{start:0,end:0}}else n=null;for(qo={focusedElem:e,selectionRange:n},ci=!1,C=t;C!==null;)if(t=C,e=t.child,(t.subtreeFlags&1028)!==0&&e!==null)e.return=t,C=e;else for(;C!==null;){t=C;try{var m=t.alternate;if(t.flags&1024)switch(t.tag){case 0:case 11:case 15:break;case 1:if(m!==null){var v=m.memoizedProps,w=m.memoizedState,d=t.stateNode,f=d.getSnapshotBeforeUpdate(t.elementType===t.type?v:Ue(t.type,v),w);d.__reactInternalSnapshotBeforeUpdate=f}break;case 3:var h=t.stateNode.containerInfo;h.nodeType===1?h.textContent="":h.nodeType===9&&h.documentElement&&h.removeChild(h.documentElement);break;case 5:case 6:case 4:case 17:break;default:throw Error(N(163))}}catch(k){X(t,t.return,k)}if(e=t.sibling,e!==null){e.return=t.return,C=e;break}C=t.return}return m=Sa,Sa=!1,m}function Xn(e,t,n){var r=t.updateQueue;if(r=r!==null?r.lastEffect:null,r!==null){var i=r=r.next;do{if((i.tag&e)===e){var o=i.destroy;i.destroy=void 0,o!==void 0&&fs(t,n,o)}i=i.next}while(i!==r)}}function Ui(e,t){if(t=t.updateQueue,t=t!==null?t.lastEffect:null,t!==null){var n=t=t.next;do{if((n.tag&e)===e){var r=n.create;n.destroy=r()}n=n.next}while(n!==t)}}function ps(e){var t=e.ref;if(t!==null){var n=e.stateNode;switch(e.tag){case 5:e=n;break;default:e=n}typeof t=="function"?t(e):t.current=e}}function Wc(e){var t=e.alternate;t!==null&&(e.alternate=null,Wc(t)),e.child=null,e.deletions=null,e.sibling=null,e.tag===5&&(t=e.stateNode,t!==null&&(delete t[Je],delete t[ar],delete t[Zo],delete t[bp],delete t[Ep])),e.stateNode=null,e.return=null,e.dependencies=null,e.memoizedProps=null,e.memoizedState=null,e.pendingProps=null,e.stateNode=null,e.updateQueue=null}function Qc(e){return e.tag===5||e.tag===3||e.tag===4}function Na(e){e:for(;;){for(;e.sibling===null;){if(e.return===null||Qc(e.return))return null;e=e.return}for(e.sibling.return=e.return,e=e.sibling;e.tag!==5&&e.tag!==6&&e.tag!==18;){if(e.flags&2||e.child===null||e.tag===4)continue e;e.child.return=e,e=e.child}if(!(e.flags&2))return e.stateNode}}function ms(e,t,n){var r=e.tag;if(r===5||r===6)e=e.stateNode,t?n.nodeType===8?n.parentNode.insertBefore(e,t):n.insertBefore(e,t):(n.nodeType===8?(t=n.parentNode,t.insertBefore(e,n)):(t=n,t.appendChild(e)),n=n._reactRootContainer,n!=null||t.onclick!==null||(t.onclick=pi));else if(r!==4&&(e=e.child,e!==null))for(ms(e,t,n),e=e.sibling;e!==null;)ms(e,t,n),e=e.sibling}function hs(e,t,n){var r=e.tag;if(r===5||r===6)e=e.stateNode,t?n.insertBefore(e,t):n.appendChild(e);else if(r!==4&&(e=e.child,e!==null))for(hs(e,t,n),e=e.sibling;e!==null;)hs(e,t,n),e=e.sibling}var ne=null,Ie=!1;function dt(e,t,n){for(n=n.child;n!==null;)Kc(e,t,n),n=n.sibling}function Kc(e,t,n){if(Ge&&typeof Ge.onCommitFiberUnmount=="function")try{Ge.onCommitFiberUnmount(Ti,n)}catch{}switch(n.tag){case 5:le||un(n,t);case 6:var r=ne,i=Ie;ne=null,dt(e,t,n),ne=r,Ie=i,ne!==null&&(Ie?(e=ne,n=n.stateNode,e.nodeType===8?e.parentNode.removeChild(n):e.removeChild(n)):ne.removeChild(n.stateNode));break;case 18:ne!==null&&(Ie?(e=ne,n=n.stateNode,e.nodeType===8?ho(e.parentNode,n):e.nodeType===1&&ho(e,n),rr(e)):ho(ne,n.stateNode));break;case 4:r=ne,i=Ie,ne=n.stateNode.containerInfo,Ie=!0,dt(e,t,n),ne=r,Ie=i;break;case 0:case 11:case 14:case 15:if(!le&&(r=n.updateQueue,r!==null&&(r=r.lastEffect,r!==null))){i=r=r.next;do{var o=i,s=o.destroy;o=o.tag,s!==void 0&&(o&2||o&4)&&fs(n,t,s),i=i.next}while(i!==r)}dt(e,t,n);break;case 1:if(!le&&(un(n,t),r=n.stateNode,typeof r.componentWillUnmount=="function"))try{r.props=n.memoizedProps,r.state=n.memoizedState,r.componentWillUnmount()}catch(l){X(n,t,l)}dt(e,t,n);break;case 21:dt(e,t,n);break;case 22:n.mode&1?(le=(r=le)||n.memoizedState!==null,dt(e,t,n),le=r):dt(e,t,n);break;default:dt(e,t,n)}}function ja(e){var t=e.updateQueue;if(t!==null){e.updateQueue=null;var n=e.stateNode;n===null&&(n=e.stateNode=new Bp),t.forEach(function(r){var i=Gp.bind(null,e,r);n.has(r)||(n.add(r),r.then(i,i))})}}function De(e,t){var n=t.deletions;if(n!==null)for(var r=0;r<n.length;r++){var i=n[r];try{var o=e,s=t,l=s;e:for(;l!==null;){switch(l.tag){case 5:ne=l.stateNode,Ie=!1;break e;case 3:ne=l.stateNode.containerInfo,Ie=!0;break e;case 4:ne=l.stateNode.containerInfo,Ie=!0;break e}l=l.return}if(ne===null)throw Error(N(160));Kc(o,s,i),ne=null,Ie=!1;var u=i.alternate;u!==null&&(u.return=null),i.return=null}catch(c){X(i,t,c)}}if(t.subtreeFlags&12854)for(t=t.child;t!==null;)Yc(t,e),t=t.sibling}function Yc(e,t){var n=e.alternate,r=e.flags;switch(e.tag){case 0:case 11:case 14:case 15:if(De(t,e),Ye(e),r&4){try{Xn(3,e,e.return),Ui(3,e)}catch(v){X(e,e.return,v)}try{Xn(5,e,e.return)}catch(v){X(e,e.return,v)}}break;case 1:De(t,e),Ye(e),r&512&&n!==null&&un(n,n.return);break;case 5:if(De(t,e),Ye(e),r&512&&n!==null&&un(n,n.return),e.flags&32){var i=e.stateNode;try{Zn(i,"")}catch(v){X(e,e.return,v)}}if(r&4&&(i=e.stateNode,i!=null)){var o=e.memoizedProps,s=n!==null?n.memoizedProps:o,l=e.type,u=e.updateQueue;if(e.updateQueue=null,u!==null)try{l==="input"&&o.type==="radio"&&o.name!=null&&gu(i,o),Uo(l,s);var c=Uo(l,o);for(s=0;s<u.length;s+=2){var p=u[s],g=u[s+1];p==="style"?ku(i,g):p==="dangerouslySetInnerHTML"?xu(i,g):p==="children"?Zn(i,g):Ts(i,p,g,c)}switch(l){case"input":Oo(i,o);break;case"textarea":yu(i,o);break;case"select":var y=i._wrapperState.wasMultiple;i._wrapperState.wasMultiple=!!o.multiple;var S=o.value;S!=null?dn(i,!!o.multiple,S,!1):y!==!!o.multiple&&(o.defaultValue!=null?dn(i,!!o.multiple,o.defaultValue,!0):dn(i,!!o.multiple,o.multiple?[]:"",!1))}i[ar]=o}catch(v){X(e,e.return,v)}}break;case 6:if(De(t,e),Ye(e),r&4){if(e.stateNode===null)throw Error(N(162));i=e.stateNode,o=e.memoizedProps;try{i.nodeValue=o}catch(v){X(e,e.return,v)}}break;case 3:if(De(t,e),Ye(e),r&4&&n!==null&&n.memoizedState.isDehydrated)try{rr(t.containerInfo)}catch(v){X(e,e.return,v)}break;case 4:De(t,e),Ye(e);break;case 13:De(t,e),Ye(e),i=e.child,i.flags&8192&&(o=i.memoizedState!==null,i.stateNode.isHidden=o,!o||i.alternate!==null&&i.alternate.memoizedState!==null||(cl=q())),r&4&&ja(e);break;case 22:if(p=n!==null&&n.memoizedState!==null,e.mode&1?(le=(c=le)||p,De(t,e),le=c):De(t,e),Ye(e),r&8192){if(c=e.memoizedState!==null,(e.stateNode.isHidden=c)&&!p&&e.mode&1)for(C=e,p=e.child;p!==null;){for(g=C=p;C!==null;){switch(y=C,S=y.child,y.tag){case 0:case 11:case 14:case 15:Xn(4,y,y.return);break;case 1:un(y,y.return);var m=y.stateNode;if(typeof m.componentWillUnmount=="function"){r=y,n=y.return;try{t=r,m.props=t.memoizedProps,m.state=t.memoizedState,m.componentWillUnmount()}catch(v){X(r,n,v)}}break;case 5:un(y,y.return);break;case 22:if(y.memoizedState!==null){Ea(g);continue}}S!==null?(S.return=y,C=S):Ea(g)}p=p.sibling}e:for(p=null,g=e;;){if(g.tag===5){if(p===null){p=g;try{i=g.stateNode,c?(o=i.style,typeof o.setProperty=="function"?o.setProperty("display","none","important"):o.display="none"):(l=g.stateNode,u=g.memoizedProps.style,s=u!=null&&u.hasOwnProperty("display")?u.display:null,l.style.display=wu("display",s))}catch(v){X(e,e.return,v)}}}else if(g.tag===6){if(p===null)try{g.stateNode.nodeValue=c?"":g.memoizedProps}catch(v){X(e,e.return,v)}}else if((g.tag!==22&&g.tag!==23||g.memoizedState===null||g===e)&&g.child!==null){g.child.return=g,g=g.child;continue}if(g===e)break e;for(;g.sibling===null;){if(g.return===null||g.return===e)break e;p===g&&(p=null),g=g.return}p===g&&(p=null),g.sibling.return=g.return,g=g.sibling}}break;case 19:De(t,e),Ye(e),r&4&&ja(e);break;case 21:break;default:De(t,e),Ye(e)}}function Ye(e){var t=e.flags;if(t&2){try{e:{for(var n=e.return;n!==null;){if(Qc(n)){var r=n;break e}n=n.return}throw Error(N(160))}switch(r.tag){case 5:var i=r.stateNode;r.flags&32&&(Zn(i,""),r.flags&=-33);var o=Na(e);hs(e,o,i);break;case 3:case 4:var s=r.stateNode.containerInfo,l=Na(e);ms(e,l,s);break;default:throw Error(N(161))}}catch(u){X(e,e.return,u)}e.flags&=-3}t&4096&&(e.flags&=-4097)}function Vp(e,t,n){C=e,Xc(e)}function Xc(e,t,n){for(var r=(e.mode&1)!==0;C!==null;){var i=C,o=i.child;if(i.tag===22&&r){var s=i.memoizedState!==null||Ir;if(!s){var l=i.alternate,u=l!==null&&l.memoizedState!==null||le;l=Ir;var c=le;if(Ir=s,(le=u)&&!c)for(C=i;C!==null;)s=C,u=s.child,s.tag===22&&s.memoizedState!==null?Ca(i):u!==null?(u.return=s,C=u):Ca(i);for(;o!==null;)C=o,Xc(o),o=o.sibling;C=i,Ir=l,le=c}ba(e)}else i.subtreeFlags&8772&&o!==null?(o.return=i,C=o):ba(e)}}function ba(e){for(;C!==null;){var t=C;if(t.flags&8772){var n=t.alternate;try{if(t.flags&8772)switch(t.tag){case 0:case 11:case 15:le||Ui(5,t);break;case 1:var r=t.stateNode;if(t.flags&4&&!le)if(n===null)r.componentDidMount();else{var i=t.elementType===t.type?n.memoizedProps:Ue(t.type,n.memoizedProps);r.componentDidUpdate(i,n.memoizedState,r.__reactInternalSnapshotBeforeUpdate)}var o=t.updateQueue;o!==null&&ua(t,o,r);break;case 3:var s=t.updateQueue;if(s!==null){if(n=null,t.child!==null)switch(t.child.tag){case 5:n=t.child.stateNode;break;case 1:n=t.child.stateNode}ua(t,s,n)}break;case 5:var l=t.stateNode;if(n===null&&t.flags&4){n=l;var u=t.memoizedProps;switch(t.type){case"button":case"input":case"select":case"textarea":u.autoFocus&&n.focus();break;case"img":u.src&&(n.src=u.src)}}break;case 6:break;case 4:break;case 12:break;case 13:if(t.memoizedState===null){var c=t.alternate;if(c!==null){var p=c.memoizedState;if(p!==null){var g=p.dehydrated;g!==null&&rr(g)}}}break;case 19:case 17:case 21:case 22:case 23:case 25:break;default:throw Error(N(163))}le||t.flags&512&&ps(t)}catch(y){X(t,t.return,y)}}if(t===e){C=null;break}if(n=t.sibling,n!==null){n.return=t.return,C=n;break}C=t.return}}function Ea(e){for(;C!==null;){var t=C;if(t===e){C=null;break}var n=t.sibling;if(n!==null){n.return=t.return,C=n;break}C=t.return}}function Ca(e){for(;C!==null;){var t=C;try{switch(t.tag){case 0:case 11:case 15:var n=t.return;try{Ui(4,t)}catch(u){X(t,n,u)}break;case 1:var r=t.stateNode;if(typeof r.componentDidMount=="function"){var i=t.return;try{r.componentDidMount()}catch(u){X(t,i,u)}}var o=t.return;try{ps(t)}catch(u){X(t,o,u)}break;case 5:var s=t.return;try{ps(t)}catch(u){X(t,s,u)}}}catch(u){X(t,t.return,u)}if(t===e){C=null;break}var l=t.sibling;if(l!==null){l.return=t.return,C=l;break}C=t.return}}var Wp=Math.ceil,ji=ut.ReactCurrentDispatcher,al=ut.ReactCurrentOwner,Oe=ut.ReactCurrentBatchConfig,A=0,te=null,J=null,re=0,Ne=0,cn=zt(0),Z=0,mr=null,Vt=0,Ii=0,ul=0,qn=null,ge=null,cl=0,Nn=1/0,et=null,bi=!1,gs=null,Nt=null,$r=!1,yt=null,Ei=0,Jn=0,ys=null,Zr=-1,ei=0;function de(){return A&6?q():Zr!==-1?Zr:Zr=q()}function jt(e){return e.mode&1?A&2&&re!==0?re&-re:_p.transition!==null?(ei===0&&(ei=Lu()),ei):(e=D,e!==0||(e=window.event,e=e===void 0?16:Iu(e.type)),e):1}function He(e,t,n,r){if(50<Jn)throw Jn=0,ys=null,Error(N(185));gr(e,n,r),(!(A&2)||e!==te)&&(e===te&&(!(A&2)&&(Ii|=n),Z===4&&ht(e,re)),we(e,r),n===1&&A===0&&!(t.mode&1)&&(Nn=q()+500,Ai&&Pt()))}function we(e,t){var n=e.callbackNode;_f(e,t);var r=ui(e,e===te?re:0);if(r===0)n!==null&&Al(n),e.callbackNode=null,e.callbackPriority=0;else if(t=r&-r,e.callbackPriority!==t){if(n!=null&&Al(n),t===1)e.tag===0?Cp(_a.bind(null,e)):oc(_a.bind(null,e)),Np(function(){!(A&6)&&Pt()}),n=null;else{switch(Ou(r)){case 1:n=As;break;case 4:n=Tu;break;case 16:n=ai;break;case 536870912:n=Ru;break;default:n=ai}n=rd(n,qc.bind(null,e))}e.callbackPriority=t,e.callbackNode=n}}function qc(e,t){if(Zr=-1,ei=0,A&6)throw Error(N(327));var n=e.callbackNode;if(gn()&&e.callbackNode!==n)return null;var r=ui(e,e===te?re:0);if(r===0)return null;if(r&30||r&e.expiredLanes||t)t=Ci(e,r);else{t=r;var i=A;A|=2;var o=Gc();(te!==e||re!==t)&&(et=null,Nn=q()+500,Dt(e,t));do try{Yp();break}catch(l){Jc(e,l)}while(!0);Xs(),ji.current=o,A=i,J!==null?t=0:(te=null,re=0,t=Z)}if(t!==0){if(t===2&&(i=Vo(e),i!==0&&(r=i,t=vs(e,i))),t===1)throw n=mr,Dt(e,0),ht(e,r),we(e,q()),n;if(t===6)ht(e,r);else{if(i=e.current.alternate,!(r&30)&&!Qp(i)&&(t=Ci(e,r),t===2&&(o=Vo(e),o!==0&&(r=o,t=vs(e,o))),t===1))throw n=mr,Dt(e,0),ht(e,r),we(e,q()),n;switch(e.finishedWork=i,e.finishedLanes=r,t){case 0:case 1:throw Error(N(345));case 2:Lt(e,ge,et);break;case 3:if(ht(e,r),(r&130023424)===r&&(t=cl+500-q(),10<t)){if(ui(e,0)!==0)break;if(i=e.suspendedLanes,(i&r)!==r){de(),e.pingedLanes|=e.suspendedLanes&i;break}e.timeoutHandle=Go(Lt.bind(null,e,ge,et),t);break}Lt(e,ge,et);break;case 4:if(ht(e,r),(r&4194240)===r)break;for(t=e.eventTimes,i=-1;0<r;){var s=31-Be(r);o=1<<s,s=t[s],s>i&&(i=s),r&=~o}if(r=i,r=q()-r,r=(120>r?120:480>r?480:1080>r?1080:1920>r?1920:3e3>r?3e3:4320>r?4320:1960*Wp(r/1960))-r,10<r){e.timeoutHandle=Go(Lt.bind(null,e,ge,et),r);break}Lt(e,ge,et);break;case 5:Lt(e,ge,et);break;default:throw Error(N(329))}}}return we(e,q()),e.callbackNode===n?qc.bind(null,e):null}function vs(e,t){var n=qn;return e.current.memoizedState.isDehydrated&&(Dt(e,t).flags|=256),e=Ci(e,t),e!==2&&(t=ge,ge=n,t!==null&&xs(t)),e}function xs(e){ge===null?ge=e:ge.push.apply(ge,e)}function Qp(e){for(var t=e;;){if(t.flags&16384){var n=t.updateQueue;if(n!==null&&(n=n.stores,n!==null))for(var r=0;r<n.length;r++){var i=n[r],o=i.getSnapshot;i=i.value;try{if(!Ve(o(),i))return!1}catch{return!1}}}if(n=t.child,t.subtreeFlags&16384&&n!==null)n.return=t,t=n;else{if(t===e)break;for(;t.sibling===null;){if(t.return===null||t.return===e)return!0;t=t.return}t.sibling.return=t.return,t=t.sibling}}return!0}function ht(e,t){for(t&=~ul,t&=~Ii,e.suspendedLanes|=t,e.pingedLanes&=~t,e=e.expirationTimes;0<t;){var n=31-Be(t),r=1<<n;e[n]=-1,t&=~r}}function _a(e){if(A&6)throw Error(N(327));gn();var t=ui(e,0);if(!(t&1))return we(e,q()),null;var n=Ci(e,t);if(e.tag!==0&&n===2){var r=Vo(e);r!==0&&(t=r,n=vs(e,r))}if(n===1)throw n=mr,Dt(e,0),ht(e,t),we(e,q()),n;if(n===6)throw Error(N(345));return e.finishedWork=e.current.alternate,e.finishedLanes=t,Lt(e,ge,et),we(e,q()),null}function dl(e,t){var n=A;A|=1;try{return e(t)}finally{A=n,A===0&&(Nn=q()+500,Ai&&Pt())}}function Wt(e){yt!==null&&yt.tag===0&&!(A&6)&&gn();var t=A;A|=1;var n=Oe.transition,r=D;try{if(Oe.transition=null,D=1,e)return e()}finally{D=r,Oe.transition=n,A=t,!(A&6)&&Pt()}}function fl(){Ne=cn.current,V(cn)}function Dt(e,t){e.finishedWork=null,e.finishedLanes=0;var n=e.timeoutHandle;if(n!==-1&&(e.timeoutHandle=-1,Sp(n)),J!==null)for(n=J.return;n!==null;){var r=n;switch(Qs(r),r.tag){case 1:r=r.type.childContextTypes,r!=null&&mi();break;case 3:kn(),V(ve),V(ue),tl();break;case 5:el(r);break;case 4:kn();break;case 13:V(Q);break;case 19:V(Q);break;case 10:qs(r.type._context);break;case 22:case 23:fl()}n=n.return}if(te=e,J=e=bt(e.current,null),re=Ne=t,Z=0,mr=null,ul=Ii=Vt=0,ge=qn=null,Ft!==null){for(t=0;t<Ft.length;t++)if(n=Ft[t],r=n.interleaved,r!==null){n.interleaved=null;var i=r.next,o=n.pending;if(o!==null){var s=o.next;o.next=i,r.next=s}n.pending=r}Ft=null}return e}function Jc(e,t){do{var n=J;try{if(Xs(),qr.current=Ni,Si){for(var r=K.memoizedState;r!==null;){var i=r.queue;i!==null&&(i.pending=null),r=r.next}Si=!1}if(Ht=0,ee=G=K=null,Yn=!1,dr=0,al.current=null,n===null||n.return===null){Z=1,mr=t,J=null;break}e:{var o=e,s=n.return,l=n,u=t;if(t=re,l.flags|=32768,u!==null&&typeof u=="object"&&typeof u.then=="function"){var c=u,p=l,g=p.tag;if(!(p.mode&1)&&(g===0||g===11||g===15)){var y=p.alternate;y?(p.updateQueue=y.updateQueue,p.memoizedState=y.memoizedState,p.lanes=y.lanes):(p.updateQueue=null,p.memoizedState=null)}var S=ha(s);if(S!==null){S.flags&=-257,ga(S,s,l,o,t),S.mode&1&&ma(o,c,t),t=S,u=c;var m=t.updateQueue;if(m===null){var v=new Set;v.add(u),t.updateQueue=v}else m.add(u);break e}else{if(!(t&1)){ma(o,c,t),pl();break e}u=Error(N(426))}}else if(W&&l.mode&1){var w=ha(s);if(w!==null){!(w.flags&65536)&&(w.flags|=256),ga(w,s,l,o,t),Ks(Sn(u,l));break e}}o=u=Sn(u,l),Z!==4&&(Z=2),qn===null?qn=[o]:qn.push(o),o=s;do{switch(o.tag){case 3:o.flags|=65536,t&=-t,o.lanes|=t;var d=Oc(o,u,t);aa(o,d);break e;case 1:l=u;var f=o.type,h=o.stateNode;if(!(o.flags&128)&&(typeof f.getDerivedStateFromError=="function"||h!==null&&typeof h.componentDidCatch=="function"&&(Nt===null||!Nt.has(h)))){o.flags|=65536,t&=-t,o.lanes|=t;var k=Fc(o,l,t);aa(o,k);break e}}o=o.return}while(o!==null)}ed(n)}catch(j){t=j,J===n&&n!==null&&(J=n=n.return);continue}break}while(!0)}function Gc(){var e=ji.current;return ji.current=Ni,e===null?Ni:e}function pl(){(Z===0||Z===3||Z===2)&&(Z=4),te===null||!(Vt&268435455)&&!(Ii&268435455)||ht(te,re)}function Ci(e,t){var n=A;A|=2;var r=Gc();(te!==e||re!==t)&&(et=null,Dt(e,t));do try{Kp();break}catch(i){Jc(e,i)}while(!0);if(Xs(),A=n,ji.current=r,J!==null)throw Error(N(261));return te=null,re=0,Z}function Kp(){for(;J!==null;)Zc(J)}function Yp(){for(;J!==null&&!xf();)Zc(J)}function Zc(e){var t=nd(e.alternate,e,Ne);e.memoizedProps=e.pendingProps,t===null?ed(e):J=t,al.current=null}function ed(e){var t=e;do{var n=t.alternate;if(e=t.return,t.flags&32768){if(n=$p(n,t),n!==null){n.flags&=32767,J=n;return}if(e!==null)e.flags|=32768,e.subtreeFlags=0,e.deletions=null;else{Z=6,J=null;return}}else if(n=Ip(n,t,Ne),n!==null){J=n;return}if(t=t.sibling,t!==null){J=t;return}J=t=e}while(t!==null);Z===0&&(Z=5)}function Lt(e,t,n){var r=D,i=Oe.transition;try{Oe.transition=null,D=1,Xp(e,t,n,r)}finally{Oe.transition=i,D=r}return null}function Xp(e,t,n,r){do gn();while(yt!==null);if(A&6)throw Error(N(327));n=e.finishedWork;var i=e.finishedLanes;if(n===null)return null;if(e.finishedWork=null,e.finishedLanes=0,n===e.current)throw Error(N(177));e.callbackNode=null,e.callbackPriority=0;var o=n.lanes|n.childLanes;if(zf(e,o),e===te&&(J=te=null,re=0),!(n.subtreeFlags&2064)&&!(n.flags&2064)||$r||($r=!0,rd(ai,function(){return gn(),null})),o=(n.flags&15990)!==0,n.subtreeFlags&15990||o){o=Oe.transition,Oe.transition=null;var s=D;D=1;var l=A;A|=4,al.current=null,Hp(e,n),Yc(n,e),hp(qo),ci=!!Xo,qo=Xo=null,e.current=n,Vp(n),wf(),A=l,D=s,Oe.transition=o}else e.current=n;if($r&&($r=!1,yt=e,Ei=i),o=e.pendingLanes,o===0&&(Nt=null),Nf(n.stateNode),we(e,q()),t!==null)for(r=e.onRecoverableError,n=0;n<t.length;n++)i=t[n],r(i.value,{componentStack:i.stack,digest:i.digest});if(bi)throw bi=!1,e=gs,gs=null,e;return Ei&1&&e.tag!==0&&gn(),o=e.pendingLanes,o&1?e===ys?Jn++:(Jn=0,ys=e):Jn=0,Pt(),null}function gn(){if(yt!==null){var e=Ou(Ei),t=Oe.transition,n=D;try{if(Oe.transition=null,D=16>e?16:e,yt===null)var r=!1;else{if(e=yt,yt=null,Ei=0,A&6)throw Error(N(331));var i=A;for(A|=4,C=e.current;C!==null;){var o=C,s=o.child;if(C.flags&16){var l=o.deletions;if(l!==null){for(var u=0;u<l.length;u++){var c=l[u];for(C=c;C!==null;){var p=C;switch(p.tag){case 0:case 11:case 15:Xn(8,p,o)}var g=p.child;if(g!==null)g.return=p,C=g;else for(;C!==null;){p=C;var y=p.sibling,S=p.return;if(Wc(p),p===c){C=null;break}if(y!==null){y.return=S,C=y;break}C=S}}}var m=o.alternate;if(m!==null){var v=m.child;if(v!==null){m.child=null;do{var w=v.sibling;v.sibling=null,v=w}while(v!==null)}}C=o}}if(o.subtreeFlags&2064&&s!==null)s.return=o,C=s;else e:for(;C!==null;){if(o=C,o.flags&2048)switch(o.tag){case 0:case 11:case 15:Xn(9,o,o.return)}var d=o.sibling;if(d!==null){d.return=o.return,C=d;break e}C=o.return}}var f=e.current;for(C=f;C!==null;){s=C;var h=s.child;if(s.subtreeFlags&2064&&h!==null)h.return=s,C=h;else e:for(s=f;C!==null;){if(l=C,l.flags&2048)try{switch(l.tag){case 0:case 11:case 15:Ui(9,l)}}catch(j){X(l,l.return,j)}if(l===s){C=null;break e}var k=l.sibling;if(k!==null){k.return=l.return,C=k;break e}C=l.return}}if(A=i,Pt(),Ge&&typeof Ge.onPostCommitFiberRoot=="function")try{Ge.onPostCommitFiberRoot(Ti,e)}catch{}r=!0}return r}finally{D=n,Oe.transition=t}}return!1}function za(e,t,n){t=Sn(n,t),t=Oc(e,t,1),e=St(e,t,1),t=de(),e!==null&&(gr(e,1,t),we(e,t))}function X(e,t,n){if(e.tag===3)za(e,e,n);else for(;t!==null;){if(t.tag===3){za(t,e,n);break}else if(t.tag===1){var r=t.stateNode;if(typeof t.type.getDerivedStateFromError=="function"||typeof r.componentDidCatch=="function"&&(Nt===null||!Nt.has(r))){e=Sn(n,e),e=Fc(t,e,1),t=St(t,e,1),e=de(),t!==null&&(gr(t,1,e),we(t,e));break}}t=t.return}}function qp(e,t,n){var r=e.pingCache;r!==null&&r.delete(t),t=de(),e.pingedLanes|=e.suspendedLanes&n,te===e&&(re&n)===n&&(Z===4||Z===3&&(re&130023424)===re&&500>q()-cl?Dt(e,0):ul|=n),we(e,t)}function td(e,t){t===0&&(e.mode&1?(t=Tr,Tr<<=1,!(Tr&130023424)&&(Tr=4194304)):t=1);var n=de();e=lt(e,t),e!==null&&(gr(e,t,n),we(e,n))}function Jp(e){var t=e.memoizedState,n=0;t!==null&&(n=t.retryLane),td(e,n)}function Gp(e,t){var n=0;switch(e.tag){case 13:var r=e.stateNode,i=e.memoizedState;i!==null&&(n=i.retryLane);break;case 19:r=e.stateNode;break;default:throw Error(N(314))}r!==null&&r.delete(t),td(e,n)}var nd;nd=function(e,t,n){if(e!==null)if(e.memoizedProps!==t.pendingProps||ve.current)ye=!0;else{if(!(e.lanes&n)&&!(t.flags&128))return ye=!1,Up(e,t,n);ye=!!(e.flags&131072)}else ye=!1,W&&t.flags&1048576&&sc(t,yi,t.index);switch(t.lanes=0,t.tag){case 2:var r=t.type;Gr(e,t),e=t.pendingProps;var i=vn(t,ue.current);hn(t,n),i=rl(null,t,r,e,i,n);var o=il();return t.flags|=1,typeof i=="object"&&i!==null&&typeof i.render=="function"&&i.$$typeof===void 0?(t.tag=1,t.memoizedState=null,t.updateQueue=null,xe(r)?(o=!0,hi(t)):o=!1,t.memoizedState=i.state!==null&&i.state!==void 0?i.state:null,Gs(t),i.updater=Di,t.stateNode=i,i._reactInternals=t,os(t,r,e,n),t=as(null,t,r,!0,o,n)):(t.tag=0,W&&o&&Ws(t),ce(null,t,i,n),t=t.child),t;case 16:r=t.elementType;e:{switch(Gr(e,t),e=t.pendingProps,i=r._init,r=i(r._payload),t.type=r,i=t.tag=em(r),e=Ue(r,e),i){case 0:t=ls(null,t,r,e,n);break e;case 1:t=xa(null,t,r,e,n);break e;case 11:t=ya(null,t,r,e,n);break e;case 14:t=va(null,t,r,Ue(r.type,e),n);break e}throw Error(N(306,r,""))}return t;case 0:return r=t.type,i=t.pendingProps,i=t.elementType===r?i:Ue(r,i),ls(e,t,r,i,n);case 1:return r=t.type,i=t.pendingProps,i=t.elementType===r?i:Ue(r,i),xa(e,t,r,i,n);case 3:e:{if(Uc(t),e===null)throw Error(N(387));r=t.pendingProps,o=t.memoizedState,i=o.element,fc(e,t),wi(t,r,null,n);var s=t.memoizedState;if(r=s.element,o.isDehydrated)if(o={element:r,isDehydrated:!1,cache:s.cache,pendingSuspenseBoundaries:s.pendingSuspenseBoundaries,transitions:s.transitions},t.updateQueue.baseState=o,t.memoizedState=o,t.flags&256){i=Sn(Error(N(423)),t),t=wa(e,t,r,n,i);break e}else if(r!==i){i=Sn(Error(N(424)),t),t=wa(e,t,r,n,i);break e}else for(je=kt(t.stateNode.containerInfo.firstChild),be=t,W=!0,$e=null,n=cc(t,null,r,n),t.child=n;n;)n.flags=n.flags&-3|4096,n=n.sibling;else{if(xn(),r===i){t=at(e,t,n);break e}ce(e,t,r,n)}t=t.child}return t;case 5:return pc(t),e===null&&ns(t),r=t.type,i=t.pendingProps,o=e!==null?e.memoizedProps:null,s=i.children,Jo(r,i)?s=null:o!==null&&Jo(r,o)&&(t.flags|=32),Dc(e,t),ce(e,t,s,n),t.child;case 6:return e===null&&ns(t),null;case 13:return Ic(e,t,n);case 4:return Zs(t,t.stateNode.containerInfo),r=t.pendingProps,e===null?t.child=wn(t,null,r,n):ce(e,t,r,n),t.child;case 11:return r=t.type,i=t.pendingProps,i=t.elementType===r?i:Ue(r,i),ya(e,t,r,i,n);case 7:return ce(e,t,t.pendingProps,n),t.child;case 8:return ce(e,t,t.pendingProps.children,n),t.child;case 12:return ce(e,t,t.pendingProps.children,n),t.child;case 10:e:{if(r=t.type._context,i=t.pendingProps,o=t.memoizedProps,s=i.value,$(vi,r._currentValue),r._currentValue=s,o!==null)if(Ve(o.value,s)){if(o.children===i.children&&!ve.current){t=at(e,t,n);break e}}else for(o=t.child,o!==null&&(o.return=t);o!==null;){var l=o.dependencies;if(l!==null){s=o.child;for(var u=l.firstContext;u!==null;){if(u.context===r){if(o.tag===1){u=it(-1,n&-n),u.tag=2;var c=o.updateQueue;if(c!==null){c=c.shared;var p=c.pending;p===null?u.next=u:(u.next=p.next,p.next=u),c.pending=u}}o.lanes|=n,u=o.alternate,u!==null&&(u.lanes|=n),rs(o.return,n,t),l.lanes|=n;break}u=u.next}}else if(o.tag===10)s=o.type===t.type?null:o.child;else if(o.tag===18){if(s=o.return,s===null)throw Error(N(341));s.lanes|=n,l=s.alternate,l!==null&&(l.lanes|=n),rs(s,n,t),s=o.sibling}else s=o.child;if(s!==null)s.return=o;else for(s=o;s!==null;){if(s===t){s=null;break}if(o=s.sibling,o!==null){o.return=s.return,s=o;break}s=s.return}o=s}ce(e,t,i.children,n),t=t.child}return t;case 9:return i=t.type,r=t.pendingProps.children,hn(t,n),i=Fe(i),r=r(i),t.flags|=1,ce(e,t,r,n),t.child;case 14:return r=t.type,i=Ue(r,t.pendingProps),i=Ue(r.type,i),va(e,t,r,i,n);case 15:return Ac(e,t,t.type,t.pendingProps,n);case 17:return r=t.type,i=t.pendingProps,i=t.elementType===r?i:Ue(r,i),Gr(e,t),t.tag=1,xe(r)?(e=!0,hi(t)):e=!1,hn(t,n),Lc(t,r,i),os(t,r,i,n),as(null,t,r,!0,e,n);case 19:return $c(e,t,n);case 22:return Mc(e,t,n)}throw Error(N(156,t.tag))};function rd(e,t){return Pu(e,t)}function Zp(e,t,n,r){this.tag=e,this.key=n,this.sibling=this.child=this.return=this.stateNode=this.type=this.elementType=null,this.index=0,this.ref=null,this.pendingProps=t,this.dependencies=this.memoizedState=this.updateQueue=this.memoizedProps=null,this.mode=r,this.subtreeFlags=this.flags=0,this.deletions=null,this.childLanes=this.lanes=0,this.alternate=null}function Re(e,t,n,r){return new Zp(e,t,n,r)}function ml(e){return e=e.prototype,!(!e||!e.isReactComponent)}function em(e){if(typeof e=="function")return ml(e)?1:0;if(e!=null){if(e=e.$$typeof,e===Ls)return 11;if(e===Os)return 14}return 2}function bt(e,t){var n=e.alternate;return n===null?(n=Re(e.tag,t,e.key,e.mode),n.elementType=e.elementType,n.type=e.type,n.stateNode=e.stateNode,n.alternate=e,e.alternate=n):(n.pendingProps=t,n.type=e.type,n.flags=0,n.subtreeFlags=0,n.deletions=null),n.flags=e.flags&14680064,n.childLanes=e.childLanes,n.lanes=e.lanes,n.child=e.child,n.memoizedProps=e.memoizedProps,n.memoizedState=e.memoizedState,n.updateQueue=e.updateQueue,t=e.dependencies,n.dependencies=t===null?null:{lanes:t.lanes,firstContext:t.firstContext},n.sibling=e.sibling,n.index=e.index,n.ref=e.ref,n}function ti(e,t,n,r,i,o){var s=2;if(r=e,typeof e=="function")ml(e)&&(s=1);else if(typeof e=="string")s=5;else e:switch(e){case Zt:return Ut(n.children,i,o,t);case Rs:s=8,i|=8;break;case zo:return e=Re(12,n,t,i|2),e.elementType=zo,e.lanes=o,e;case Po:return e=Re(13,n,t,i),e.elementType=Po,e.lanes=o,e;case To:return e=Re(19,n,t,i),e.elementType=To,e.lanes=o,e;case pu:return $i(n,i,o,t);default:if(typeof e=="object"&&e!==null)switch(e.$$typeof){case du:s=10;break e;case fu:s=9;break e;case Ls:s=11;break e;case Os:s=14;break e;case ft:s=16,r=null;break e}throw Error(N(130,e==null?e:typeof e,""))}return t=Re(s,n,t,i),t.elementType=e,t.type=r,t.lanes=o,t}function Ut(e,t,n,r){return e=Re(7,e,r,t),e.lanes=n,e}function $i(e,t,n,r){return e=Re(22,e,r,t),e.elementType=pu,e.lanes=n,e.stateNode={isHidden:!1},e}function No(e,t,n){return e=Re(6,e,null,t),e.lanes=n,e}function jo(e,t,n){return t=Re(4,e.children!==null?e.children:[],e.key,t),t.lanes=n,t.stateNode={containerInfo:e.containerInfo,pendingChildren:null,implementation:e.implementation},t}function tm(e,t,n,r,i){this.tag=t,this.containerInfo=e,this.finishedWork=this.pingCache=this.current=this.pendingChildren=null,this.timeoutHandle=-1,this.callbackNode=this.pendingContext=this.context=null,this.callbackPriority=0,this.eventTimes=ro(0),this.expirationTimes=ro(-1),this.entangledLanes=this.finishedLanes=this.mutableReadLanes=this.expiredLanes=this.pingedLanes=this.suspendedLanes=this.pendingLanes=0,this.entanglements=ro(0),this.identifierPrefix=r,this.onRecoverableError=i,this.mutableSourceEagerHydrationData=null}function hl(e,t,n,r,i,o,s,l,u){return e=new tm(e,t,n,l,u),t===1?(t=1,o===!0&&(t|=8)):t=0,o=Re(3,null,null,t),e.current=o,o.stateNode=e,o.memoizedState={element:r,isDehydrated:n,cache:null,transitions:null,pendingSuspenseBoundaries:null},Gs(o),e}function nm(e,t,n){var r=3<arguments.length&&arguments[3]!==void 0?arguments[3]:null;return{$$typeof:Gt,key:r==null?null:""+r,children:e,containerInfo:t,implementation:n}}function id(e){if(!e)return Ct;e=e._reactInternals;e:{if(Yt(e)!==e||e.tag!==1)throw Error(N(170));var t=e;do{switch(t.tag){case 3:t=t.stateNode.context;break e;case 1:if(xe(t.type)){t=t.stateNode.__reactInternalMemoizedMergedChildContext;break e}}t=t.return}while(t!==null);throw Error(N(171))}if(e.tag===1){var n=e.type;if(xe(n))return ic(e,n,t)}return t}function od(e,t,n,r,i,o,s,l,u){return e=hl(n,r,!0,e,i,o,s,l,u),e.context=id(null),n=e.current,r=de(),i=jt(n),o=it(r,i),o.callback=t??null,St(n,o,i),e.current.lanes=i,gr(e,i,r),we(e,r),e}function Bi(e,t,n,r){var i=t.current,o=de(),s=jt(i);return n=id(n),t.context===null?t.context=n:t.pendingContext=n,t=it(o,s),t.payload={element:e},r=r===void 0?null:r,r!==null&&(t.callback=r),e=St(i,t,s),e!==null&&(He(e,i,s,o),Xr(e,i,s)),s}function _i(e){if(e=e.current,!e.child)return null;switch(e.child.tag){case 5:return e.child.stateNode;default:return e.child.stateNode}}function Pa(e,t){if(e=e.memoizedState,e!==null&&e.dehydrated!==null){var n=e.retryLane;e.retryLane=n!==0&&n<t?n:t}}function gl(e,t){Pa(e,t),(e=e.alternate)&&Pa(e,t)}function rm(){return null}var sd=typeof reportError=="function"?reportError:function(e){console.error(e)};function yl(e){this._internalRoot=e}Hi.prototype.render=yl.prototype.render=function(e){var t=this._internalRoot;if(t===null)throw Error(N(409));Bi(e,t,null,null)};Hi.prototype.unmount=yl.prototype.unmount=function(){var e=this._internalRoot;if(e!==null){this._internalRoot=null;var t=e.containerInfo;Wt(function(){Bi(null,e,null,null)}),t[st]=null}};function Hi(e){this._internalRoot=e}Hi.prototype.unstable_scheduleHydration=function(e){if(e){var t=Mu();e={blockedOn:null,target:e,priority:t};for(var n=0;n<mt.length&&t!==0&&t<mt[n].priority;n++);mt.splice(n,0,e),n===0&&Uu(e)}};function vl(e){return!(!e||e.nodeType!==1&&e.nodeType!==9&&e.nodeType!==11)}function Vi(e){return!(!e||e.nodeType!==1&&e.nodeType!==9&&e.nodeType!==11&&(e.nodeType!==8||e.nodeValue!==" react-mount-point-unstable "))}function Ta(){}function im(e,t,n,r,i){if(i){if(typeof r=="function"){var o=r;r=function(){var c=_i(s);o.call(c)}}var s=od(t,r,e,0,null,!1,!1,"",Ta);return e._reactRootContainer=s,e[st]=s.current,sr(e.nodeType===8?e.parentNode:e),Wt(),s}for(;i=e.lastChild;)e.removeChild(i);if(typeof r=="function"){var l=r;r=function(){var c=_i(u);l.call(c)}}var u=hl(e,0,!1,null,null,!1,!1,"",Ta);return e._reactRootContainer=u,e[st]=u.current,sr(e.nodeType===8?e.parentNode:e),Wt(function(){Bi(t,u,n,r)}),u}function Wi(e,t,n,r,i){var o=n._reactRootContainer;if(o){var s=o;if(typeof i=="function"){var l=i;i=function(){var u=_i(s);l.call(u)}}Bi(t,s,e,i)}else s=im(n,t,e,i,r);return _i(s)}Fu=function(e){switch(e.tag){case 3:var t=e.stateNode;if(t.current.memoizedState.isDehydrated){var n=$n(t.pendingLanes);n!==0&&(Ms(t,n|1),we(t,q()),!(A&6)&&(Nn=q()+500,Pt()))}break;case 13:Wt(function(){var r=lt(e,1);if(r!==null){var i=de();He(r,e,1,i)}}),gl(e,1)}};Ds=function(e){if(e.tag===13){var t=lt(e,134217728);if(t!==null){var n=de();He(t,e,134217728,n)}gl(e,134217728)}};Au=function(e){if(e.tag===13){var t=jt(e),n=lt(e,t);if(n!==null){var r=de();He(n,e,t,r)}gl(e,t)}};Mu=function(){return D};Du=function(e,t){var n=D;try{return D=e,t()}finally{D=n}};$o=function(e,t,n){switch(t){case"input":if(Oo(e,n),t=n.name,n.type==="radio"&&t!=null){for(n=e;n.parentNode;)n=n.parentNode;for(n=n.querySelectorAll("input[name="+JSON.stringify(""+t)+'][type="radio"]'),t=0;t<n.length;t++){var r=n[t];if(r!==e&&r.form===e.form){var i=Fi(r);if(!i)throw Error(N(90));hu(r),Oo(r,i)}}}break;case"textarea":yu(e,n);break;case"select":t=n.value,t!=null&&dn(e,!!n.multiple,t,!1)}};ju=dl;bu=Wt;var om={usingClientEntryPoint:!1,Events:[vr,rn,Fi,Su,Nu,dl]},Mn={findFiberByHostInstance:Ot,bundleType:0,version:"18.3.1",rendererPackageName:"react-dom"},sm={bundleType:Mn.bundleType,version:Mn.version,rendererPackageName:Mn.rendererPackageName,rendererConfig:Mn.rendererConfig,overrideHookState:null,overrideHookStateDeletePath:null,overrideHookStateRenamePath:null,overrideProps:null,overridePropsDeletePath:null,overridePropsRenamePath:null,setErrorHandler:null,setSuspenseHandler:null,scheduleUpdate:null,currentDispatcherRef:ut.ReactCurrentDispatcher,findHostInstanceByFiber:function(e){return e=_u(e),e===null?null:e.stateNode},findFiberByHostInstance:Mn.findFiberByHostInstance||rm,findHostInstancesForRefresh:null,scheduleRefresh:null,scheduleRoot:null,setRefreshHandler:null,getCurrentFiber:null,reconcilerVersion:"18.3.1-next-f1338f8080-20240426"};if(typeof __REACT_DEVTOOLS_GLOBAL_HOOK__<"u"){var Br=__REACT_DEVTOOLS_GLOBAL_HOOK__;if(!Br.isDisabled&&Br.supportsFiber)try{Ti=Br.inject(sm),Ge=Br}catch{}}Ce.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED=om;Ce.createPortal=function(e,t){var n=2<arguments.length&&arguments[2]!==void 0?arguments[2]:null;if(!vl(t))throw Error(N(200));return nm(e,t,null,n)};Ce.createRoot=function(e,t){if(!vl(e))throw Error(N(299));var n=!1,r="",i=sd;return t!=null&&(t.unstable_strictMode===!0&&(n=!0),t.identifierPrefix!==void 0&&(r=t.identifierPrefix),t.onRecoverableError!==void 0&&(i=t.onRecoverableError)),t=hl(e,1,!1,null,null,n,!1,r,i),e[st]=t.current,sr(e.nodeType===8?e.parentNode:e),new yl(t)};Ce.findDOMNode=function(e){if(e==null)return null;if(e.nodeType===1)return e;var t=e._reactInternals;if(t===void 0)throw typeof e.render=="function"?Error(N(188)):(e=Object.keys(e).join(","),Error(N(268,e)));return e=_u(t),e=e===null?null:e.stateNode,e};Ce.flushSync=function(e){return Wt(e)};Ce.hydrate=function(e,t,n){if(!Vi(t))throw Error(N(200));return Wi(null,e,t,!0,n)};Ce.hydrateRoot=function(e,t,n){if(!vl(e))throw Error(N(405));var r=n!=null&&n.hydratedSources||null,i=!1,o="",s=sd;if(n!=null&&(n.unstable_strictMode===!0&&(i=!0),n.identifierPrefix!==void 0&&(o=n.identifierPrefix),n.onRecoverableError!==void 0&&(s=n.onRecoverableError)),t=od(t,null,e,1,n??null,i,!1,o,s),e[st]=t.current,sr(e),r)for(e=0;e<r.length;e++)n=r[e],i=n._getVersion,i=i(n._source),t.mutableSourceEagerHydrationData==null?t.mutableSourceEagerHydrationData=[n,i]:t.mutableSourceEagerHydrationData.push(n,i);return new Hi(t)};Ce.render=function(e,t,n){if(!Vi(t))throw Error(N(200));return Wi(null,e,t,!1,n)};Ce.unmountComponentAtNode=function(e){if(!Vi(e))throw Error(N(40));return e._reactRootContainer?(Wt(function(){Wi(null,null,e,!1,function(){e._reactRootContainer=null,e[st]=null})}),!0):!1};Ce.unstable_batchedUpdates=dl;Ce.unstable_renderSubtreeIntoContainer=function(e,t,n,r){if(!Vi(n))throw Error(N(200));if(e==null||e._reactInternals===void 0)throw Error(N(38));return Wi(e,t,n,!1,r)};Ce.version="18.3.1-next-f1338f8080-20240426";function ld(){if(!(typeof __REACT_DEVTOOLS_GLOBAL_HOOK__>"u"||typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE!="function"))try{__REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE(ld)}catch(e){console.error(e)}}ld(),lu.exports=Ce;var lm=lu.exports,xl,Ra=lm;xl=Ra.createRoot,Ra.hydrateRoot;function ad(e,t){return function(){return e.apply(t,arguments)}}const{toString:am}=Object.prototype,{getPrototypeOf:wl}=Object,{iterator:Qi,toStringTag:ud}=Symbol,Ki=(e=>t=>{const n=am.call(t);return e[n]||(e[n]=n.slice(8,-1).toLowerCase())})(Object.create(null)),We=e=>(e=e.toLowerCase(),t=>Ki(t)===e),Yi=e=>t=>typeof t===e,{isArray:_n}=Array,jn=Yi("undefined");function wr(e){return e!==null&&!jn(e)&&e.constructor!==null&&!jn(e.constructor)&&ke(e.constructor.isBuffer)&&e.constructor.isBuffer(e)}const cd=We("ArrayBuffer");function um(e){let t;return typeof ArrayBuffer<"u"&&ArrayBuffer.isView?t=ArrayBuffer.isView(e):t=e&&e.buffer&&cd(e.buffer),t}const cm=Yi("string"),ke=Yi("function"),dd=Yi("number"),kr=e=>e!==null&&typeof e=="object",dm=e=>e===!0||e===!1,ni=e=>{if(Ki(e)!=="object")return!1;const t=wl(e);return(t===null||t===Object.prototype||Object.getPrototypeOf(t)===null)&&!(ud in e)&&!(Qi in e)},fm=e=>{if(!kr(e)||wr(e))return!1;try{return Object.keys(e).length===0&&Object.getPrototypeOf(e)===Object.prototype}catch{return!1}},pm=We("Date"),mm=We("File"),hm=We("Blob"),gm=We("FileList"),ym=e=>kr(e)&&ke(e.pipe),vm=e=>{let t;return e&&(typeof FormData=="function"&&e instanceof FormData||ke(e.append)&&((t=Ki(e))==="formdata"||t==="object"&&ke(e.toString)&&e.toString()==="[object FormData]"))},xm=We("URLSearchParams"),[wm,km,Sm,Nm]=["ReadableStream","Request","Response","Headers"].map(We),jm=e=>e.trim?e.trim():e.replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g,"");function Sr(e,t,{allOwnKeys:n=!1}={}){if(e===null||typeof e>"u")return;let r,i;if(typeof e!="object"&&(e=[e]),_n(e))for(r=0,i=e.length;r<i;r++)t.call(null,e[r],r,e);else{if(wr(e))return;const o=n?Object.getOwnPropertyNames(e):Object.keys(e),s=o.length;let l;for(r=0;r<s;r++)l=o[r],t.call(null,e[l],l,e)}}function fd(e,t){if(wr(e))return null;t=t.toLowerCase();const n=Object.keys(e);let r=n.length,i;for(;r-- >0;)if(i=n[r],t===i.toLowerCase())return i;return null}const Mt=typeof globalThis<"u"?globalThis:typeof self<"u"?self:typeof window<"u"?window:global,pd=e=>!jn(e)&&e!==Mt;function ws(){const{caseless:e,skipUndefined:t}=pd(this)&&this||{},n={},r=(i,o)=>{const s=e&&fd(n,o)||o;ni(n[s])&&ni(i)?n[s]=ws(n[s],i):ni(i)?n[s]=ws({},i):_n(i)?n[s]=i.slice():(!t||!jn(i))&&(n[s]=i)};for(let i=0,o=arguments.length;i<o;i++)arguments[i]&&Sr(arguments[i],r);return n}const bm=(e,t,n,{allOwnKeys:r}={})=>(Sr(t,(i,o)=>{n&&ke(i)?e[o]=ad(i,n):e[o]=i},{allOwnKeys:r}),e),Em=e=>(e.charCodeAt(0)===65279&&(e=e.slice(1)),e),Cm=(e,t,n,r)=>{e.prototype=Object.create(t.prototype,r),e.prototype.constructor=e,Object.defineProperty(e,"super",{value:t.prototype}),n&&Object.assign(e.prototype,n)},_m=(e,t,n,r)=>{let i,o,s;const l={};if(t=t||{},e==null)return t;do{for(i=Object.getOwnPropertyNames(e),o=i.length;o-- >0;)s=i[o],(!r||r(s,e,t))&&!l[s]&&(t[s]=e[s],l[s]=!0);e=n!==!1&&wl(e)}while(e&&(!n||n(e,t))&&e!==Object.prototype);return t},zm=(e,t,n)=>{e=String(e),(n===void 0||n>e.length)&&(n=e.length),n-=t.length;const r=e.indexOf(t,n);return r!==-1&&r===n},Pm=e=>{if(!e)return null;if(_n(e))return e;let t=e.length;if(!dd(t))return null;const n=new Array(t);for(;t-- >0;)n[t]=e[t];return n},Tm=(e=>t=>e&&t instanceof e)(typeof Uint8Array<"u"&&wl(Uint8Array)),Rm=(e,t)=>{const r=(e&&e[Qi]).call(e);let i;for(;(i=r.next())&&!i.done;){const o=i.value;t.call(e,o[0],o[1])}},Lm=(e,t)=>{let n;const r=[];for(;(n=e.exec(t))!==null;)r.push(n);return r},Om=We("HTMLFormElement"),Fm=e=>e.toLowerCase().replace(/[-_\s]([a-z\d])(\w*)/g,function(n,r,i){return r.toUpperCase()+i}),La=(({hasOwnProperty:e})=>(t,n)=>e.call(t,n))(Object.prototype),Am=We("RegExp"),md=(e,t)=>{const n=Object.getOwnPropertyDescriptors(e),r={};Sr(n,(i,o)=>{let s;(s=t(i,o,e))!==!1&&(r[o]=s||i)}),Object.defineProperties(e,r)},Mm=e=>{md(e,(t,n)=>{if(ke(e)&&["arguments","caller","callee"].indexOf(n)!==-1)return!1;const r=e[n];if(ke(r)){if(t.enumerable=!1,"writable"in t){t.writable=!1;return}t.set||(t.set=()=>{throw Error("Can not rewrite read-only method '"+n+"'")})}})},Dm=(e,t)=>{const n={},r=i=>{i.forEach(o=>{n[o]=!0})};return _n(e)?r(e):r(String(e).split(t)),n},Um=()=>{},Im=(e,t)=>e!=null&&Number.isFinite(e=+e)?e:t;function $m(e){return!!(e&&ke(e.append)&&e[ud]==="FormData"&&e[Qi])}const Bm=e=>{const t=new Array(10),n=(r,i)=>{if(kr(r)){if(t.indexOf(r)>=0)return;if(wr(r))return r;if(!("toJSON"in r)){t[i]=r;const o=_n(r)?[]:{};return Sr(r,(s,l)=>{const u=n(s,i+1);!jn(u)&&(o[l]=u)}),t[i]=void 0,o}}return r};return n(e,0)},Hm=We("AsyncFunction"),Vm=e=>e&&(kr(e)||ke(e))&&ke(e.then)&&ke(e.catch),hd=((e,t)=>e?setImmediate:t?((n,r)=>(Mt.addEventListener("message",({source:i,data:o})=>{i===Mt&&o===n&&r.length&&r.shift()()},!1),i=>{r.push(i),Mt.postMessage(n,"*")}))(`axios@${Math.random()}`,[]):n=>setTimeout(n))(typeof setImmediate=="function",ke(Mt.postMessage)),Wm=typeof queueMicrotask<"u"?queueMicrotask.bind(Mt):typeof process<"u"&&process.nextTick||hd,Qm=e=>e!=null&&ke(e[Qi]),x={isArray:_n,isArrayBuffer:cd,isBuffer:wr,isFormData:vm,isArrayBufferView:um,isString:cm,isNumber:dd,isBoolean:dm,isObject:kr,isPlainObject:ni,isEmptyObject:fm,isReadableStream:wm,isRequest:km,isResponse:Sm,isHeaders:Nm,isUndefined:jn,isDate:pm,isFile:mm,isBlob:hm,isRegExp:Am,isFunction:ke,isStream:ym,isURLSearchParams:xm,isTypedArray:Tm,isFileList:gm,forEach:Sr,merge:ws,extend:bm,trim:jm,stripBOM:Em,inherits:Cm,toFlatObject:_m,kindOf:Ki,kindOfTest:We,endsWith:zm,toArray:Pm,forEachEntry:Rm,matchAll:Lm,isHTMLForm:Om,hasOwnProperty:La,hasOwnProp:La,reduceDescriptors:md,freezeMethods:Mm,toObjectSet:Dm,toCamelCase:Fm,noop:Um,toFiniteNumber:Im,findKey:fd,global:Mt,isContextDefined:pd,isSpecCompliantForm:$m,toJSONObject:Bm,isAsyncFn:Hm,isThenable:Vm,setImmediate:hd,asap:Wm,isIterable:Qm};function T(e,t,n,r,i){Error.call(this),Error.captureStackTrace?Error.captureStackTrace(this,this.constructor):this.stack=new Error().stack,this.message=e,this.name="AxiosError",t&&(this.code=t),n&&(this.config=n),r&&(this.request=r),i&&(this.response=i,this.status=i.status?i.status:null)}x.inherits(T,Error,{toJSON:function(){return{message:this.message,name:this.name,description:this.description,number:this.number,fileName:this.fileName,lineNumber:this.lineNumber,columnNumber:this.columnNumber,stack:this.stack,config:x.toJSONObject(this.config),code:this.code,status:this.status}}});const gd=T.prototype,yd={};["ERR_BAD_OPTION_VALUE","ERR_BAD_OPTION","ECONNABORTED","ETIMEDOUT","ERR_NETWORK","ERR_FR_TOO_MANY_REDIRECTS","ERR_DEPRECATED","ERR_BAD_RESPONSE","ERR_BAD_REQUEST","ERR_CANCELED","ERR_NOT_SUPPORT","ERR_INVALID_URL"].forEach(e=>{yd[e]={value:e}});Object.defineProperties(T,yd);Object.defineProperty(gd,"isAxiosError",{value:!0});T.from=(e,t,n,r,i,o)=>{const s=Object.create(gd);x.toFlatObject(e,s,function(p){return p!==Error.prototype},c=>c!=="isAxiosError");const l=e&&e.message?e.message:"Error",u=t==null&&e?e.code:t;return T.call(s,l,u,n,r,i),e&&s.cause==null&&Object.defineProperty(s,"cause",{value:e,configurable:!0}),s.name=e&&e.name||"Error",o&&Object.assign(s,o),s};const Km=null;function ks(e){return x.isPlainObject(e)||x.isArray(e)}function vd(e){return x.endsWith(e,"[]")?e.slice(0,-2):e}function Oa(e,t,n){return e?e.concat(t).map(function(i,o){return i=vd(i),!n&&o?"["+i+"]":i}).join(n?".":""):t}function Ym(e){return x.isArray(e)&&!e.some(ks)}const Xm=x.toFlatObject(x,{},null,function(t){return/^is[A-Z]/.test(t)});function Xi(e,t,n){if(!x.isObject(e))throw new TypeError("target must be an object");t=t||new FormData,n=x.toFlatObject(n,{metaTokens:!0,dots:!1,indexes:!1},!1,function(v,w){return!x.isUndefined(w[v])});const r=n.metaTokens,i=n.visitor||p,o=n.dots,s=n.indexes,u=(n.Blob||typeof Blob<"u"&&Blob)&&x.isSpecCompliantForm(t);if(!x.isFunction(i))throw new TypeError("visitor must be a function");function c(m){if(m===null)return"";if(x.isDate(m))return m.toISOString();if(x.isBoolean(m))return m.toString();if(!u&&x.isBlob(m))throw new T("Blob is not supported. Use a Buffer instead.");return x.isArrayBuffer(m)||x.isTypedArray(m)?u&&typeof Blob=="function"?new Blob([m]):Buffer.from(m):m}function p(m,v,w){let d=m;if(m&&!w&&typeof m=="object"){if(x.endsWith(v,"{}"))v=r?v:v.slice(0,-2),m=JSON.stringify(m);else if(x.isArray(m)&&Ym(m)||(x.isFileList(m)||x.endsWith(v,"[]"))&&(d=x.toArray(m)))return v=vd(v),d.forEach(function(h,k){!(x.isUndefined(h)||h===null)&&t.append(s===!0?Oa([v],k,o):s===null?v:v+"[]",c(h))}),!1}return ks(m)?!0:(t.append(Oa(w,v,o),c(m)),!1)}const g=[],y=Object.assign(Xm,{defaultVisitor:p,convertValue:c,isVisitable:ks});function S(m,v){if(!x.isUndefined(m)){if(g.indexOf(m)!==-1)throw Error("Circular reference detected in "+v.join("."));g.push(m),x.forEach(m,function(d,f){(!(x.isUndefined(d)||d===null)&&i.call(t,d,x.isString(f)?f.trim():f,v,y))===!0&&S(d,v?v.concat(f):[f])}),g.pop()}}if(!x.isObject(e))throw new TypeError("data must be an object");return S(e),t}function Fa(e){const t={"!":"%21","'":"%27","(":"%28",")":"%29","~":"%7E","%20":"+","%00":"\0"};return encodeURIComponent(e).replace(/[!'()~]|%20|%00/g,function(r){return t[r]})}function kl(e,t){this._pairs=[],e&&Xi(e,this,t)}const xd=kl.prototype;xd.append=function(t,n){this._pairs.push([t,n])};xd.toString=function(t){const n=t?function(r){return t.call(this,r,Fa)}:Fa;return this._pairs.map(function(i){return n(i[0])+"="+n(i[1])},"").join("&")};function qm(e){return encodeURIComponent(e).replace(/%3A/gi,":").replace(/%24/g,"$").replace(/%2C/gi,",").replace(/%20/g,"+")}function wd(e,t,n){if(!t)return e;const r=n&&n.encode||qm;x.isFunction(n)&&(n={serialize:n});const i=n&&n.serialize;let o;if(i?o=i(t,n):o=x.isURLSearchParams(t)?t.toString():new kl(t,n).toString(r),o){const s=e.indexOf("#");s!==-1&&(e=e.slice(0,s)),e+=(e.indexOf("?")===-1?"?":"&")+o}return e}class Aa{constructor(){this.handlers=[]}use(t,n,r){return this.handlers.push({fulfilled:t,rejected:n,synchronous:r?r.synchronous:!1,runWhen:r?r.runWhen:null}),this.handlers.length-1}eject(t){this.handlers[t]&&(this.handlers[t]=null)}clear(){this.handlers&&(this.handlers=[])}forEach(t){x.forEach(this.handlers,function(r){r!==null&&t(r)})}}const kd={silentJSONParsing:!0,forcedJSONParsing:!0,clarifyTimeoutError:!1},Jm=typeof URLSearchParams<"u"?URLSearchParams:kl,Gm=typeof FormData<"u"?FormData:null,Zm=typeof Blob<"u"?Blob:null,eh={isBrowser:!0,classes:{URLSearchParams:Jm,FormData:Gm,Blob:Zm},protocols:["http","https","file","blob","url","data"]},Sl=typeof window<"u"&&typeof document<"u",Ss=typeof navigator=="object"&&navigator||void 0,th=Sl&&(!Ss||["ReactNative","NativeScript","NS"].indexOf(Ss.product)<0),nh=typeof WorkerGlobalScope<"u"&&self instanceof WorkerGlobalScope&&typeof self.importScripts=="function",rh=Sl&&window.location.href||"http://localhost",ih=Object.freeze(Object.defineProperty({__proto__:null,hasBrowserEnv:Sl,hasStandardBrowserEnv:th,hasStandardBrowserWebWorkerEnv:nh,navigator:Ss,origin:rh},Symbol.toStringTag,{value:"Module"})),ae={...ih,...eh};function oh(e,t){return Xi(e,new ae.classes.URLSearchParams,{visitor:function(n,r,i,o){return ae.isNode&&x.isBuffer(n)?(this.append(r,n.toString("base64")),!1):o.defaultVisitor.apply(this,arguments)},...t})}function sh(e){return x.matchAll(/\w+|\[(\w*)]/g,e).map(t=>t[0]==="[]"?"":t[1]||t[0])}function lh(e){const t={},n=Object.keys(e);let r;const i=n.length;let o;for(r=0;r<i;r++)o=n[r],t[o]=e[o];return t}function Sd(e){function t(n,r,i,o){let s=n[o++];if(s==="__proto__")return!0;const l=Number.isFinite(+s),u=o>=n.length;return s=!s&&x.isArray(i)?i.length:s,u?(x.hasOwnProp(i,s)?i[s]=[i[s],r]:i[s]=r,!l):((!i[s]||!x.isObject(i[s]))&&(i[s]=[]),t(n,r,i[s],o)&&x.isArray(i[s])&&(i[s]=lh(i[s])),!l)}if(x.isFormData(e)&&x.isFunction(e.entries)){const n={};return x.forEachEntry(e,(r,i)=>{t(sh(r),i,n,0)}),n}return null}function ah(e,t,n){if(x.isString(e))try{return(t||JSON.parse)(e),x.trim(e)}catch(r){if(r.name!=="SyntaxError")throw r}return(n||JSON.stringify)(e)}const Nr={transitional:kd,adapter:["xhr","http","fetch"],transformRequest:[function(t,n){const r=n.getContentType()||"",i=r.indexOf("application/json")>-1,o=x.isObject(t);if(o&&x.isHTMLForm(t)&&(t=new FormData(t)),x.isFormData(t))return i?JSON.stringify(Sd(t)):t;if(x.isArrayBuffer(t)||x.isBuffer(t)||x.isStream(t)||x.isFile(t)||x.isBlob(t)||x.isReadableStream(t))return t;if(x.isArrayBufferView(t))return t.buffer;if(x.isURLSearchParams(t))return n.setContentType("application/x-www-form-urlencoded;charset=utf-8",!1),t.toString();let l;if(o){if(r.indexOf("application/x-www-form-urlencoded")>-1)return oh(t,this.formSerializer).toString();if((l=x.isFileList(t))||r.indexOf("multipart/form-data")>-1){const u=this.env&&this.env.FormData;return Xi(l?{"files[]":t}:t,u&&new u,this.formSerializer)}}return o||i?(n.setContentType("application/json",!1),ah(t)):t}],transformResponse:[function(t){const n=this.transitional||Nr.transitional,r=n&&n.forcedJSONParsing,i=this.responseType==="json";if(x.isResponse(t)||x.isReadableStream(t))return t;if(t&&x.isString(t)&&(r&&!this.responseType||i)){const s=!(n&&n.silentJSONParsing)&&i;try{return JSON.parse(t,this.parseReviver)}catch(l){if(s)throw l.name==="SyntaxError"?T.from(l,T.ERR_BAD_RESPONSE,this,null,this.response):l}}return t}],timeout:0,xsrfCookieName:"XSRF-TOKEN",xsrfHeaderName:"X-XSRF-TOKEN",maxContentLength:-1,maxBodyLength:-1,env:{FormData:ae.classes.FormData,Blob:ae.classes.Blob},validateStatus:function(t){return t>=200&&t<300},headers:{common:{Accept:"application/json, text/plain, */*","Content-Type":void 0}}};x.forEach(["delete","get","head","post","put","patch"],e=>{Nr.headers[e]={}});const uh=x.toObjectSet(["age","authorization","content-length","content-type","etag","expires","from","host","if-modified-since","if-unmodified-since","last-modified","location","max-forwards","proxy-authorization","referer","retry-after","user-agent"]),ch=e=>{const t={};let n,r,i;return e&&e.split(`
`).forEach(function(s){i=s.indexOf(":"),n=s.substring(0,i).trim().toLowerCase(),r=s.substring(i+1).trim(),!(!n||t[n]&&uh[n])&&(n==="set-cookie"?t[n]?t[n].push(r):t[n]=[r]:t[n]=t[n]?t[n]+", "+r:r)}),t},Ma=Symbol("internals");function Dn(e){return e&&String(e).trim().toLowerCase()}function ri(e){return e===!1||e==null?e:x.isArray(e)?e.map(ri):String(e)}function dh(e){const t=Object.create(null),n=/([^\s,;=]+)\s*(?:=\s*([^,;]+))?/g;let r;for(;r=n.exec(e);)t[r[1]]=r[2];return t}const fh=e=>/^[-_a-zA-Z0-9^`|~,!#$%&'*+.]+$/.test(e.trim());function bo(e,t,n,r,i){if(x.isFunction(r))return r.call(this,t,n);if(i&&(t=n),!!x.isString(t)){if(x.isString(r))return t.indexOf(r)!==-1;if(x.isRegExp(r))return r.test(t)}}function ph(e){return e.trim().toLowerCase().replace(/([a-z\d])(\w*)/g,(t,n,r)=>n.toUpperCase()+r)}function mh(e,t){const n=x.toCamelCase(" "+t);["get","set","has"].forEach(r=>{Object.defineProperty(e,r+n,{value:function(i,o,s){return this[r].call(this,t,i,o,s)},configurable:!0})})}let Se=class{constructor(t){t&&this.set(t)}set(t,n,r){const i=this;function o(l,u,c){const p=Dn(u);if(!p)throw new Error("header name must be a non-empty string");const g=x.findKey(i,p);(!g||i[g]===void 0||c===!0||c===void 0&&i[g]!==!1)&&(i[g||u]=ri(l))}const s=(l,u)=>x.forEach(l,(c,p)=>o(c,p,u));if(x.isPlainObject(t)||t instanceof this.constructor)s(t,n);else if(x.isString(t)&&(t=t.trim())&&!fh(t))s(ch(t),n);else if(x.isObject(t)&&x.isIterable(t)){let l={},u,c;for(const p of t){if(!x.isArray(p))throw TypeError("Object iterator must return a key-value pair");l[c=p[0]]=(u=l[c])?x.isArray(u)?[...u,p[1]]:[u,p[1]]:p[1]}s(l,n)}else t!=null&&o(n,t,r);return this}get(t,n){if(t=Dn(t),t){const r=x.findKey(this,t);if(r){const i=this[r];if(!n)return i;if(n===!0)return dh(i);if(x.isFunction(n))return n.call(this,i,r);if(x.isRegExp(n))return n.exec(i);throw new TypeError("parser must be boolean|regexp|function")}}}has(t,n){if(t=Dn(t),t){const r=x.findKey(this,t);return!!(r&&this[r]!==void 0&&(!n||bo(this,this[r],r,n)))}return!1}delete(t,n){const r=this;let i=!1;function o(s){if(s=Dn(s),s){const l=x.findKey(r,s);l&&(!n||bo(r,r[l],l,n))&&(delete r[l],i=!0)}}return x.isArray(t)?t.forEach(o):o(t),i}clear(t){const n=Object.keys(this);let r=n.length,i=!1;for(;r--;){const o=n[r];(!t||bo(this,this[o],o,t,!0))&&(delete this[o],i=!0)}return i}normalize(t){const n=this,r={};return x.forEach(this,(i,o)=>{const s=x.findKey(r,o);if(s){n[s]=ri(i),delete n[o];return}const l=t?ph(o):String(o).trim();l!==o&&delete n[o],n[l]=ri(i),r[l]=!0}),this}concat(...t){return this.constructor.concat(this,...t)}toJSON(t){const n=Object.create(null);return x.forEach(this,(r,i)=>{r!=null&&r!==!1&&(n[i]=t&&x.isArray(r)?r.join(", "):r)}),n}[Symbol.iterator](){return Object.entries(this.toJSON())[Symbol.iterator]()}toString(){return Object.entries(this.toJSON()).map(([t,n])=>t+": "+n).join(`
`)}getSetCookie(){return this.get("set-cookie")||[]}get[Symbol.toStringTag](){return"AxiosHeaders"}static from(t){return t instanceof this?t:new this(t)}static concat(t,...n){const r=new this(t);return n.forEach(i=>r.set(i)),r}static accessor(t){const r=(this[Ma]=this[Ma]={accessors:{}}).accessors,i=this.prototype;function o(s){const l=Dn(s);r[l]||(mh(i,s),r[l]=!0)}return x.isArray(t)?t.forEach(o):o(t),this}};Se.accessor(["Content-Type","Content-Length","Accept","Accept-Encoding","User-Agent","Authorization"]);x.reduceDescriptors(Se.prototype,({value:e},t)=>{let n=t[0].toUpperCase()+t.slice(1);return{get:()=>e,set(r){this[n]=r}}});x.freezeMethods(Se);function Eo(e,t){const n=this||Nr,r=t||n,i=Se.from(r.headers);let o=r.data;return x.forEach(e,function(l){o=l.call(n,o,i.normalize(),t?t.status:void 0)}),i.normalize(),o}function Nd(e){return!!(e&&e.__CANCEL__)}function zn(e,t,n){T.call(this,e??"canceled",T.ERR_CANCELED,t,n),this.name="CanceledError"}x.inherits(zn,T,{__CANCEL__:!0});function jd(e,t,n){const r=n.config.validateStatus;!n.status||!r||r(n.status)?e(n):t(new T("Request failed with status code "+n.status,[T.ERR_BAD_REQUEST,T.ERR_BAD_RESPONSE][Math.floor(n.status/100)-4],n.config,n.request,n))}function hh(e){const t=/^([-+\w]{1,25})(:?\/\/|:)/.exec(e);return t&&t[1]||""}function gh(e,t){e=e||10;const n=new Array(e),r=new Array(e);let i=0,o=0,s;return t=t!==void 0?t:1e3,function(u){const c=Date.now(),p=r[o];s||(s=c),n[i]=u,r[i]=c;let g=o,y=0;for(;g!==i;)y+=n[g++],g=g%e;if(i=(i+1)%e,i===o&&(o=(o+1)%e),c-s<t)return;const S=p&&c-p;return S?Math.round(y*1e3/S):void 0}}function yh(e,t){let n=0,r=1e3/t,i,o;const s=(c,p=Date.now())=>{n=p,i=null,o&&(clearTimeout(o),o=null),e(...c)};return[(...c)=>{const p=Date.now(),g=p-n;g>=r?s(c,p):(i=c,o||(o=setTimeout(()=>{o=null,s(i)},r-g)))},()=>i&&s(i)]}const zi=(e,t,n=3)=>{let r=0;const i=gh(50,250);return yh(o=>{const s=o.loaded,l=o.lengthComputable?o.total:void 0,u=s-r,c=i(u),p=s<=l;r=s;const g={loaded:s,total:l,progress:l?s/l:void 0,bytes:u,rate:c||void 0,estimated:c&&l&&p?(l-s)/c:void 0,event:o,lengthComputable:l!=null,[t?"download":"upload"]:!0};e(g)},n)},Da=(e,t)=>{const n=e!=null;return[r=>t[0]({lengthComputable:n,total:e,loaded:r}),t[1]]},Ua=e=>(...t)=>x.asap(()=>e(...t)),vh=ae.hasStandardBrowserEnv?((e,t)=>n=>(n=new URL(n,ae.origin),e.protocol===n.protocol&&e.host===n.host&&(t||e.port===n.port)))(new URL(ae.origin),ae.navigator&&/(msie|trident)/i.test(ae.navigator.userAgent)):()=>!0,xh=ae.hasStandardBrowserEnv?{write(e,t,n,r,i,o){const s=[e+"="+encodeURIComponent(t)];x.isNumber(n)&&s.push("expires="+new Date(n).toGMTString()),x.isString(r)&&s.push("path="+r),x.isString(i)&&s.push("domain="+i),o===!0&&s.push("secure"),document.cookie=s.join("; ")},read(e){const t=document.cookie.match(new RegExp("(^|;\\s*)("+e+")=([^;]*)"));return t?decodeURIComponent(t[3]):null},remove(e){this.write(e,"",Date.now()-864e5)}}:{write(){},read(){return null},remove(){}};function wh(e){return/^([a-z][a-z\d+\-.]*:)?\/\//i.test(e)}function kh(e,t){return t?e.replace(/\/?\/$/,"")+"/"+t.replace(/^\/+/,""):e}function bd(e,t,n){let r=!wh(t);return e&&(r||n==!1)?kh(e,t):t}const Ia=e=>e instanceof Se?{...e}:e;function Qt(e,t){t=t||{};const n={};function r(c,p,g,y){return x.isPlainObject(c)&&x.isPlainObject(p)?x.merge.call({caseless:y},c,p):x.isPlainObject(p)?x.merge({},p):x.isArray(p)?p.slice():p}function i(c,p,g,y){if(x.isUndefined(p)){if(!x.isUndefined(c))return r(void 0,c,g,y)}else return r(c,p,g,y)}function o(c,p){if(!x.isUndefined(p))return r(void 0,p)}function s(c,p){if(x.isUndefined(p)){if(!x.isUndefined(c))return r(void 0,c)}else return r(void 0,p)}function l(c,p,g){if(g in t)return r(c,p);if(g in e)return r(void 0,c)}const u={url:o,method:o,data:o,baseURL:s,transformRequest:s,transformResponse:s,paramsSerializer:s,timeout:s,timeoutMessage:s,withCredentials:s,withXSRFToken:s,adapter:s,responseType:s,xsrfCookieName:s,xsrfHeaderName:s,onUploadProgress:s,onDownloadProgress:s,decompress:s,maxContentLength:s,maxBodyLength:s,beforeRedirect:s,transport:s,httpAgent:s,httpsAgent:s,cancelToken:s,socketPath:s,responseEncoding:s,validateStatus:l,headers:(c,p,g)=>i(Ia(c),Ia(p),g,!0)};return x.forEach(Object.keys({...e,...t}),function(p){const g=u[p]||i,y=g(e[p],t[p],p);x.isUndefined(y)&&g!==l||(n[p]=y)}),n}const Ed=e=>{const t=Qt({},e);let{data:n,withXSRFToken:r,xsrfHeaderName:i,xsrfCookieName:o,headers:s,auth:l}=t;if(t.headers=s=Se.from(s),t.url=wd(bd(t.baseURL,t.url,t.allowAbsoluteUrls),e.params,e.paramsSerializer),l&&s.set("Authorization","Basic "+btoa((l.username||"")+":"+(l.password?unescape(encodeURIComponent(l.password)):""))),x.isFormData(n)){if(ae.hasStandardBrowserEnv||ae.hasStandardBrowserWebWorkerEnv)s.setContentType(void 0);else if(x.isFunction(n.getHeaders)){const u=n.getHeaders(),c=["content-type","content-length"];Object.entries(u).forEach(([p,g])=>{c.includes(p.toLowerCase())&&s.set(p,g)})}}if(ae.hasStandardBrowserEnv&&(r&&x.isFunction(r)&&(r=r(t)),r||r!==!1&&vh(t.url))){const u=i&&o&&xh.read(o);u&&s.set(i,u)}return t},Sh=typeof XMLHttpRequest<"u",Nh=Sh&&function(e){return new Promise(function(n,r){const i=Ed(e);let o=i.data;const s=Se.from(i.headers).normalize();let{responseType:l,onUploadProgress:u,onDownloadProgress:c}=i,p,g,y,S,m;function v(){S&&S(),m&&m(),i.cancelToken&&i.cancelToken.unsubscribe(p),i.signal&&i.signal.removeEventListener("abort",p)}let w=new XMLHttpRequest;w.open(i.method.toUpperCase(),i.url,!0),w.timeout=i.timeout;function d(){if(!w)return;const h=Se.from("getAllResponseHeaders"in w&&w.getAllResponseHeaders()),j={data:!l||l==="text"||l==="json"?w.responseText:w.response,status:w.status,statusText:w.statusText,headers:h,config:e,request:w};jd(function(E){n(E),v()},function(E){r(E),v()},j),w=null}"onloadend"in w?w.onloadend=d:w.onreadystatechange=function(){!w||w.readyState!==4||w.status===0&&!(w.responseURL&&w.responseURL.indexOf("file:")===0)||setTimeout(d)},w.onabort=function(){w&&(r(new T("Request aborted",T.ECONNABORTED,e,w)),w=null)},w.onerror=function(k){const j=k&&k.message?k.message:"Network Error",_=new T(j,T.ERR_NETWORK,e,w);_.event=k||null,r(_),w=null},w.ontimeout=function(){let k=i.timeout?"timeout of "+i.timeout+"ms exceeded":"timeout exceeded";const j=i.transitional||kd;i.timeoutErrorMessage&&(k=i.timeoutErrorMessage),r(new T(k,j.clarifyTimeoutError?T.ETIMEDOUT:T.ECONNABORTED,e,w)),w=null},o===void 0&&s.setContentType(null),"setRequestHeader"in w&&x.forEach(s.toJSON(),function(k,j){w.setRequestHeader(j,k)}),x.isUndefined(i.withCredentials)||(w.withCredentials=!!i.withCredentials),l&&l!=="json"&&(w.responseType=i.responseType),c&&([y,m]=zi(c,!0),w.addEventListener("progress",y)),u&&w.upload&&([g,S]=zi(u),w.upload.addEventListener("progress",g),w.upload.addEventListener("loadend",S)),(i.cancelToken||i.signal)&&(p=h=>{w&&(r(!h||h.type?new zn(null,e,w):h),w.abort(),w=null)},i.cancelToken&&i.cancelToken.subscribe(p),i.signal&&(i.signal.aborted?p():i.signal.addEventListener("abort",p)));const f=hh(i.url);if(f&&ae.protocols.indexOf(f)===-1){r(new T("Unsupported protocol "+f+":",T.ERR_BAD_REQUEST,e));return}w.send(o||null)})},jh=(e,t)=>{const{length:n}=e=e?e.filter(Boolean):[];if(t||n){let r=new AbortController,i;const o=function(c){if(!i){i=!0,l();const p=c instanceof Error?c:this.reason;r.abort(p instanceof T?p:new zn(p instanceof Error?p.message:p))}};let s=t&&setTimeout(()=>{s=null,o(new T(`timeout ${t} of ms exceeded`,T.ETIMEDOUT))},t);const l=()=>{e&&(s&&clearTimeout(s),s=null,e.forEach(c=>{c.unsubscribe?c.unsubscribe(o):c.removeEventListener("abort",o)}),e=null)};e.forEach(c=>c.addEventListener("abort",o));const{signal:u}=r;return u.unsubscribe=()=>x.asap(l),u}},bh=function*(e,t){let n=e.byteLength;if(n<t){yield e;return}let r=0,i;for(;r<n;)i=r+t,yield e.slice(r,i),r=i},Eh=async function*(e,t){for await(const n of Ch(e))yield*bh(n,t)},Ch=async function*(e){if(e[Symbol.asyncIterator]){yield*e;return}const t=e.getReader();try{for(;;){const{done:n,value:r}=await t.read();if(n)break;yield r}}finally{await t.cancel()}},$a=(e,t,n,r)=>{const i=Eh(e,t);let o=0,s,l=u=>{s||(s=!0,r&&r(u))};return new ReadableStream({async pull(u){try{const{done:c,value:p}=await i.next();if(c){l(),u.close();return}let g=p.byteLength;if(n){let y=o+=g;n(y)}u.enqueue(new Uint8Array(p))}catch(c){throw l(c),c}},cancel(u){return l(u),i.return()}},{highWaterMark:2})},Ba=64*1024,{isFunction:Hr}=x,_h=(({Request:e,Response:t})=>({Request:e,Response:t}))(x.global),{ReadableStream:Ha,TextEncoder:Va}=x.global,Wa=(e,...t)=>{try{return!!e(...t)}catch{return!1}},zh=e=>{e=x.merge.call({skipUndefined:!0},_h,e);const{fetch:t,Request:n,Response:r}=e,i=t?Hr(t):typeof fetch=="function",o=Hr(n),s=Hr(r);if(!i)return!1;const l=i&&Hr(Ha),u=i&&(typeof Va=="function"?(m=>v=>m.encode(v))(new Va):async m=>new Uint8Array(await new n(m).arrayBuffer())),c=o&&l&&Wa(()=>{let m=!1;const v=new n(ae.origin,{body:new Ha,method:"POST",get duplex(){return m=!0,"half"}}).headers.has("Content-Type");return m&&!v}),p=s&&l&&Wa(()=>x.isReadableStream(new r("").body)),g={stream:p&&(m=>m.body)};i&&["text","arrayBuffer","blob","formData","stream"].forEach(m=>{!g[m]&&(g[m]=(v,w)=>{let d=v&&v[m];if(d)return d.call(v);throw new T(`Response type '${m}' is not supported`,T.ERR_NOT_SUPPORT,w)})});const y=async m=>{if(m==null)return 0;if(x.isBlob(m))return m.size;if(x.isSpecCompliantForm(m))return(await new n(ae.origin,{method:"POST",body:m}).arrayBuffer()).byteLength;if(x.isArrayBufferView(m)||x.isArrayBuffer(m))return m.byteLength;if(x.isURLSearchParams(m)&&(m=m+""),x.isString(m))return(await u(m)).byteLength},S=async(m,v)=>{const w=x.toFiniteNumber(m.getContentLength());return w??y(v)};return async m=>{let{url:v,method:w,data:d,signal:f,cancelToken:h,timeout:k,onDownloadProgress:j,onUploadProgress:_,responseType:E,headers:z,withCredentials:I="same-origin",fetchOptions:L}=Ed(m),me=t||fetch;E=E?(E+"").toLowerCase():"text";let Qe=jh([f,h&&h.toAbortSignal()],k),Me=null;const Ke=Qe&&Qe.unsubscribe&&(()=>{Qe.unsubscribe()});let jr;try{if(_&&c&&w!=="get"&&w!=="head"&&(jr=await S(z,d))!==0){let M=new n(v,{method:"POST",body:d,duplex:"half"}),B;if(x.isFormData(d)&&(B=M.headers.get("content-type"))&&z.setContentType(B),M.body){const[ct,ze]=Da(jr,zi(Ua(_)));d=$a(M.body,Ba,ct,ze)}}x.isString(I)||(I=I?"include":"omit");const he=o&&"credentials"in n.prototype,Xt={...L,signal:Qe,method:w.toUpperCase(),headers:z.normalize().toJSON(),body:d,duplex:"half",credentials:he?I:void 0};Me=o&&new n(v,Xt);let b=await(o?me(Me,L):me(v,Xt));const P=p&&(E==="stream"||E==="response");if(p&&(j||P&&Ke)){const M={};["status","statusText","headers"].forEach(qt=>{M[qt]=b[qt]});const B=x.toFiniteNumber(b.headers.get("content-length")),[ct,ze]=j&&Da(B,zi(Ua(j),!0))||[];b=new r($a(b.body,Ba,ct,()=>{ze&&ze(),Ke&&Ke()}),M)}E=E||"text";let R=await g[x.findKey(g,E)||"text"](b,m);return!P&&Ke&&Ke(),await new Promise((M,B)=>{jd(M,B,{data:R,headers:Se.from(b.headers),status:b.status,statusText:b.statusText,config:m,request:Me})})}catch(he){throw Ke&&Ke(),he&&he.name==="TypeError"&&/Load failed|fetch/i.test(he.message)?Object.assign(new T("Network Error",T.ERR_NETWORK,m,Me),{cause:he.cause||he}):T.from(he,he&&he.code,m,Me)}}},Ph=new Map,Cd=e=>{let t=e?e.env:{};const{fetch:n,Request:r,Response:i}=t,o=[r,i,n];let s=o.length,l=s,u,c,p=Ph;for(;l--;)u=o[l],c=p.get(u),c===void 0&&p.set(u,c=l?new Map:zh(t)),p=c;return c};Cd();const Ns={http:Km,xhr:Nh,fetch:{get:Cd}};x.forEach(Ns,(e,t)=>{if(e){try{Object.defineProperty(e,"name",{value:t})}catch{}Object.defineProperty(e,"adapterName",{value:t})}});const Qa=e=>`- ${e}`,Th=e=>x.isFunction(e)||e===null||e===!1,_d={getAdapter:(e,t)=>{e=x.isArray(e)?e:[e];const{length:n}=e;let r,i;const o={};for(let s=0;s<n;s++){r=e[s];let l;if(i=r,!Th(r)&&(i=Ns[(l=String(r)).toLowerCase()],i===void 0))throw new T(`Unknown adapter '${l}'`);if(i&&(x.isFunction(i)||(i=i.get(t))))break;o[l||"#"+s]=i}if(!i){const s=Object.entries(o).map(([u,c])=>`adapter ${u} `+(c===!1?"is not supported by the environment":"is not available in the build"));let l=n?s.length>1?`since :
`+s.map(Qa).join(`
`):" "+Qa(s[0]):"as no adapter specified";throw new T("There is no suitable adapter to dispatch the request "+l,"ERR_NOT_SUPPORT")}return i},adapters:Ns};function Co(e){if(e.cancelToken&&e.cancelToken.throwIfRequested(),e.signal&&e.signal.aborted)throw new zn(null,e)}function Ka(e){return Co(e),e.headers=Se.from(e.headers),e.data=Eo.call(e,e.transformRequest),["post","put","patch"].indexOf(e.method)!==-1&&e.headers.setContentType("application/x-www-form-urlencoded",!1),_d.getAdapter(e.adapter||Nr.adapter,e)(e).then(function(r){return Co(e),r.data=Eo.call(e,e.transformResponse,r),r.headers=Se.from(r.headers),r},function(r){return Nd(r)||(Co(e),r&&r.response&&(r.response.data=Eo.call(e,e.transformResponse,r.response),r.response.headers=Se.from(r.response.headers))),Promise.reject(r)})}const zd="1.12.2",qi={};["object","boolean","number","function","string","symbol"].forEach((e,t)=>{qi[e]=function(r){return typeof r===e||"a"+(t<1?"n ":" ")+e}});const Ya={};qi.transitional=function(t,n,r){function i(o,s){return"[Axios v"+zd+"] Transitional option '"+o+"'"+s+(r?". "+r:"")}return(o,s,l)=>{if(t===!1)throw new T(i(s," has been removed"+(n?" in "+n:"")),T.ERR_DEPRECATED);return n&&!Ya[s]&&(Ya[s]=!0,console.warn(i(s," has been deprecated since v"+n+" and will be removed in the near future"))),t?t(o,s,l):!0}};qi.spelling=function(t){return(n,r)=>(console.warn(`${r} is likely a misspelling of ${t}`),!0)};function Rh(e,t,n){if(typeof e!="object")throw new T("options must be an object",T.ERR_BAD_OPTION_VALUE);const r=Object.keys(e);let i=r.length;for(;i-- >0;){const o=r[i],s=t[o];if(s){const l=e[o],u=l===void 0||s(l,o,e);if(u!==!0)throw new T("option "+o+" must be "+u,T.ERR_BAD_OPTION_VALUE);continue}if(n!==!0)throw new T("Unknown option "+o,T.ERR_BAD_OPTION)}}const ii={assertOptions:Rh,validators:qi},Xe=ii.validators;let It=class{constructor(t){this.defaults=t||{},this.interceptors={request:new Aa,response:new Aa}}async request(t,n){try{return await this._request(t,n)}catch(r){if(r instanceof Error){let i={};Error.captureStackTrace?Error.captureStackTrace(i):i=new Error;const o=i.stack?i.stack.replace(/^.+\n/,""):"";try{r.stack?o&&!String(r.stack).endsWith(o.replace(/^.+\n.+\n/,""))&&(r.stack+=`
`+o):r.stack=o}catch{}}throw r}}_request(t,n){typeof t=="string"?(n=n||{},n.url=t):n=t||{},n=Qt(this.defaults,n);const{transitional:r,paramsSerializer:i,headers:o}=n;r!==void 0&&ii.assertOptions(r,{silentJSONParsing:Xe.transitional(Xe.boolean),forcedJSONParsing:Xe.transitional(Xe.boolean),clarifyTimeoutError:Xe.transitional(Xe.boolean)},!1),i!=null&&(x.isFunction(i)?n.paramsSerializer={serialize:i}:ii.assertOptions(i,{encode:Xe.function,serialize:Xe.function},!0)),n.allowAbsoluteUrls!==void 0||(this.defaults.allowAbsoluteUrls!==void 0?n.allowAbsoluteUrls=this.defaults.allowAbsoluteUrls:n.allowAbsoluteUrls=!0),ii.assertOptions(n,{baseUrl:Xe.spelling("baseURL"),withXsrfToken:Xe.spelling("withXSRFToken")},!0),n.method=(n.method||this.defaults.method||"get").toLowerCase();let s=o&&x.merge(o.common,o[n.method]);o&&x.forEach(["delete","get","head","post","put","patch","common"],m=>{delete o[m]}),n.headers=Se.concat(s,o);const l=[];let u=!0;this.interceptors.request.forEach(function(v){typeof v.runWhen=="function"&&v.runWhen(n)===!1||(u=u&&v.synchronous,l.unshift(v.fulfilled,v.rejected))});const c=[];this.interceptors.response.forEach(function(v){c.push(v.fulfilled,v.rejected)});let p,g=0,y;if(!u){const m=[Ka.bind(this),void 0];for(m.unshift(...l),m.push(...c),y=m.length,p=Promise.resolve(n);g<y;)p=p.then(m[g++],m[g++]);return p}y=l.length;let S=n;for(;g<y;){const m=l[g++],v=l[g++];try{S=m(S)}catch(w){v.call(this,w);break}}try{p=Ka.call(this,S)}catch(m){return Promise.reject(m)}for(g=0,y=c.length;g<y;)p=p.then(c[g++],c[g++]);return p}getUri(t){t=Qt(this.defaults,t);const n=bd(t.baseURL,t.url,t.allowAbsoluteUrls);return wd(n,t.params,t.paramsSerializer)}};x.forEach(["delete","get","head","options"],function(t){It.prototype[t]=function(n,r){return this.request(Qt(r||{},{method:t,url:n,data:(r||{}).data}))}});x.forEach(["post","put","patch"],function(t){function n(r){return function(o,s,l){return this.request(Qt(l||{},{method:t,headers:r?{"Content-Type":"multipart/form-data"}:{},url:o,data:s}))}}It.prototype[t]=n(),It.prototype[t+"Form"]=n(!0)});let Lh=class Pd{constructor(t){if(typeof t!="function")throw new TypeError("executor must be a function.");let n;this.promise=new Promise(function(o){n=o});const r=this;this.promise.then(i=>{if(!r._listeners)return;let o=r._listeners.length;for(;o-- >0;)r._listeners[o](i);r._listeners=null}),this.promise.then=i=>{let o;const s=new Promise(l=>{r.subscribe(l),o=l}).then(i);return s.cancel=function(){r.unsubscribe(o)},s},t(function(o,s,l){r.reason||(r.reason=new zn(o,s,l),n(r.reason))})}throwIfRequested(){if(this.reason)throw this.reason}subscribe(t){if(this.reason){t(this.reason);return}this._listeners?this._listeners.push(t):this._listeners=[t]}unsubscribe(t){if(!this._listeners)return;const n=this._listeners.indexOf(t);n!==-1&&this._listeners.splice(n,1)}toAbortSignal(){const t=new AbortController,n=r=>{t.abort(r)};return this.subscribe(n),t.signal.unsubscribe=()=>this.unsubscribe(n),t.signal}static source(){let t;return{token:new Pd(function(i){t=i}),cancel:t}}};function Oh(e){return function(n){return e.apply(null,n)}}function Fh(e){return x.isObject(e)&&e.isAxiosError===!0}const js={Continue:100,SwitchingProtocols:101,Processing:102,EarlyHints:103,Ok:200,Created:201,Accepted:202,NonAuthoritativeInformation:203,NoContent:204,ResetContent:205,PartialContent:206,MultiStatus:207,AlreadyReported:208,ImUsed:226,MultipleChoices:300,MovedPermanently:301,Found:302,SeeOther:303,NotModified:304,UseProxy:305,Unused:306,TemporaryRedirect:307,PermanentRedirect:308,BadRequest:400,Unauthorized:401,PaymentRequired:402,Forbidden:403,NotFound:404,MethodNotAllowed:405,NotAcceptable:406,ProxyAuthenticationRequired:407,RequestTimeout:408,Conflict:409,Gone:410,LengthRequired:411,PreconditionFailed:412,PayloadTooLarge:413,UriTooLong:414,UnsupportedMediaType:415,RangeNotSatisfiable:416,ExpectationFailed:417,ImATeapot:418,MisdirectedRequest:421,UnprocessableEntity:422,Locked:423,FailedDependency:424,TooEarly:425,UpgradeRequired:426,PreconditionRequired:428,TooManyRequests:429,RequestHeaderFieldsTooLarge:431,UnavailableForLegalReasons:451,InternalServerError:500,NotImplemented:501,BadGateway:502,ServiceUnavailable:503,GatewayTimeout:504,HttpVersionNotSupported:505,VariantAlsoNegotiates:506,InsufficientStorage:507,LoopDetected:508,NotExtended:510,NetworkAuthenticationRequired:511};Object.entries(js).forEach(([e,t])=>{js[t]=e});function Td(e){const t=new It(e),n=ad(It.prototype.request,t);return x.extend(n,It.prototype,t,{allOwnKeys:!0}),x.extend(n,t,null,{allOwnKeys:!0}),n.create=function(i){return Td(Qt(e,i))},n}const U=Td(Nr);U.Axios=It;U.CanceledError=zn;U.CancelToken=Lh;U.isCancel=Nd;U.VERSION=zd;U.toFormData=Xi;U.AxiosError=T;U.Cancel=U.CanceledError;U.all=function(t){return Promise.all(t)};U.spread=Oh;U.isAxiosError=Fh;U.mergeConfig=Qt;U.AxiosHeaders=Se;U.formToJSON=e=>Sd(x.isHTMLForm(e)?new FormData(e):e);U.getAdapter=_d.getAdapter;U.HttpStatusCode=js;U.default=U;const{Axios:Yh,AxiosError:Xh,CanceledError:qh,isCancel:Jh,CancelToken:Gh,VERSION:Zh,all:eg,Cancel:tg,isAxiosError:ng,spread:rg,toFormData:ig,AxiosHeaders:og,HttpStatusCode:sg,formToJSON:lg,getAdapter:ag,mergeConfig:ug}=U;class Ah{constructor(){Ji(this,"baseURL");Ji(this,"authToken",null);this.baseURL="/api",this.initializeAuth()}initializeAuth(){var n;const t=localStorage.getItem("auth_token")||((n=document.querySelector('meta[name="csrf-token"]'))==null?void 0:n.getAttribute("content"));t&&this.setAuthToken(t)}setAuthToken(t){this.authToken=t,U.defaults.headers.common.Authorization=`Bearer ${t}`}clearAuthToken(){this.authToken=null,delete U.defaults.headers.common.Authorization,localStorage.removeItem("auth_token")}getConfig(t={}){var i;const n={baseURL:this.baseURL,headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest"}},r=(i=document.querySelector('meta[name="csrf-token"]'))==null?void 0:i.getAttribute("content");return r&&(n.headers["X-CSRF-TOKEN"]=r),this.authToken&&(n.headers.Authorization=`Bearer ${this.authToken}`),{...n,...t}}async get(t,n){var r;try{return await U.get(t,this.getConfig(n))}catch(i){throw((r=i.response)==null?void 0:r.status)===401&&this.handleUnauthorized(),this.handleError(i)}}async post(t,n,r){var i;try{return await U.post(t,n,this.getConfig(r))}catch(o){throw((i=o.response)==null?void 0:i.status)===401&&this.handleUnauthorized(),this.handleError(o)}}async put(t,n,r){var i;try{return await U.put(t,n,this.getConfig(r))}catch(o){throw((i=o.response)==null?void 0:i.status)===401&&this.handleUnauthorized(),this.handleError(o)}}async delete(t,n){var r;try{return await U.delete(t,this.getConfig(n))}catch(i){throw((r=i.response)==null?void 0:r.status)===401&&this.handleUnauthorized(),this.handleError(i)}}async patch(t,n,r){var i;try{return await U.patch(t,n,this.getConfig(r))}catch(o){throw((i=o.response)==null?void 0:i.status)===401&&this.handleUnauthorized(),this.handleError(o)}}handleError(t){var n,r;if(t.response){const i=((n=t.response.data)==null?void 0:n.message)||((r=t.response.data)==null?void 0:r.error)||`HTTP Error ${t.response.status}`;return new Error(i)}else return t.request?new Error("Network error - please check your connection"):new Error(t.message||"An unexpected error occurred")}handleUnauthorized(){this.clearAuthToken(),window.dispatchEvent(new CustomEvent("auth:unauthorized"))}async fetchPaginated(t,n=1,r=20){return this.get(`${t}?page=${n}&per_page=${r}`)}async uploadFile(t,n,r){const i=new FormData;i.append("file",n);const o={headers:{"Content-Type":"multipart/form-data"},onUploadProgress:s=>{if(r&&s.total){const l=Math.round(s.loaded*100/s.total);r(l)}}};return this.post(t,i,o)}async login(t,n){var i;const r=await this.post("/auth/login",{email:t,password:n});return r.data.success&&((i=r.data.data)!=null&&i.token)&&(this.setAuthToken(r.data.data.token),localStorage.setItem("auth_token",r.data.data.token)),r}async logout(){try{await this.post("/auth/logout")}catch{}finally{this.clearAuthToken()}}async getCurrentUser(){return this.get("/me")}async saveVideoProgress(t,n,r){return this.post("/video-progress",{movie_id:t,progress:n,duration:r,device:navigator.userAgent,platform:"web"})}async getVideoProgress(t){return this.get(`/video-progress/${t}`)}async getWatchHistory(t=1,n=20){return this.fetchPaginated("/watch-history",t,n)}}const Le=new Ah,Xa=({data:e,onRefresh:t})=>{if(!e)return a.jsx("div",{children:"Loading dashboard..."});const n=i=>new Date(i).toLocaleDateString("en-US",{year:"numeric",month:"short",day:"numeric"}),r=i=>new Date(i).toLocaleDateString("en-US",{year:"numeric",month:"long"});return a.jsxs("div",{className:"dashboard-tab",children:[a.jsxs("div",{className:"dashboard-header",children:[a.jsxs("h2",{className:"dashboard-title",children:[a.jsx("span",{className:"title-icon",children:"👋"}),"Welcome back, ",e.user.name,"!"]}),a.jsx("button",{className:"refresh-button",onClick:t,title:"Refresh Dashboard",children:"🔄"})]}),a.jsxs("div",{className:"stats-grid",children:[a.jsxs("div",{className:"stat-card",children:[a.jsx("div",{className:"stat-icon",children:"📺"}),a.jsxs("div",{className:"stat-content",children:[a.jsx("h3",{className:"stat-number",children:e.stats.watchlist_count}),a.jsx("p",{className:"stat-label",children:"Movies in Watchlist"})]})]}),a.jsxs("div",{className:"stat-card",children:[a.jsx("div",{className:"stat-icon",children:"❤️"}),a.jsxs("div",{className:"stat-content",children:[a.jsx("h3",{className:"stat-number",children:e.stats.likes_count}),a.jsx("p",{className:"stat-label",children:"Liked Movies"})]})]}),a.jsxs("div",{className:"stat-card",children:[a.jsx("div",{className:"stat-icon",children:"🕒"}),a.jsxs("div",{className:"stat-content",children:[a.jsx("h3",{className:"stat-number",children:e.stats.watch_history_count}),a.jsx("p",{className:"stat-label",children:"Movies Watched"})]})]}),a.jsxs("div",{className:"stat-card",children:[a.jsx("div",{className:"stat-icon",children:"👤"}),a.jsxs("div",{className:"stat-content",children:[a.jsx("h3",{className:"stat-number",children:"Member"}),a.jsxs("p",{className:"stat-label",children:["Since ",r(e.user.member_since)]})]})]})]}),a.jsxs("div",{className:"activity-section",children:[a.jsxs("h3",{className:"section-title",children:[a.jsx("span",{className:"section-icon",children:"⚡"}),"Recent Activity"]}),a.jsxs("div",{className:"activity-grid",children:[a.jsxs("div",{className:"activity-card",children:[a.jsxs("h4",{className:"activity-title",children:[a.jsx("span",{className:"activity-icon",children:"▶️"}),"Continue Watching"]}),e.recent_activity.recent_watched.length>0?a.jsx("div",{className:"activity-list",children:e.recent_activity.recent_watched.map(i=>a.jsxs("div",{className:"activity-item",children:[a.jsxs("div",{className:"item-thumbnail",children:[a.jsx("img",{src:i.thumbnail,alt:i.title,onError:o=>{o.target.style.display="none"}}),a.jsx("div",{className:"progress-bar",children:a.jsx("div",{className:"progress-fill",style:{width:`${Math.min(Math.max(i.progress,0),100)}%`}})})]}),a.jsxs("div",{className:"item-details",children:[a.jsx("h5",{className:"item-title",children:i.title}),a.jsxs("p",{className:"item-meta",children:[Math.round(i.progress),"% • ",n(i.last_watched)]})]})]},i.movie_id))}):a.jsx("div",{className:"empty-state",children:a.jsx("p",{children:"No recent viewing activity"})})]}),a.jsxs("div",{className:"activity-card",children:[a.jsxs("h4",{className:"activity-title",children:[a.jsx("span",{className:"activity-icon",children:"❤️"}),"Recently Liked"]}),e.recent_activity.recent_likes.length>0?a.jsx("div",{className:"activity-list",children:e.recent_activity.recent_likes.map(i=>a.jsxs("div",{className:"activity-item",children:[a.jsx("div",{className:"item-thumbnail",children:a.jsx("img",{src:i.thumbnail,alt:i.title,onError:o=>{o.target.style.display="none"}})}),a.jsxs("div",{className:"item-details",children:[a.jsx("h5",{className:"item-title",children:i.title}),a.jsxs("p",{className:"item-meta",children:["Liked on ",n(i.liked_at)]})]})]},i.movie_id))}):a.jsx("div",{className:"empty-state",children:a.jsx("p",{children:"No liked movies yet"})})]})]})]}),a.jsx("style",{jsx:!0,children:`
        .dashboard-tab {
          height: 100%;
          overflow-y: auto;
        }

        .dashboard-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dashboard-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .title-icon {
          font-size: 1.75rem;
        }

        .refresh-button {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          color: #ffffff;
          border-radius: 8px;
          padding: 0.5rem 1rem;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1rem;
        }

        .refresh-button:hover {
          background: rgba(255, 255, 255, 0.2);
          transform: scale(1.05);
        }

        .stats-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
          gap: 1.5rem;
          margin-bottom: 3rem;
        }

        .stat-card {
          background: rgba(255, 255, 255, 0.1);
          backdrop-filter: blur(10px);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 12px;
          padding: 2rem;
          display: flex;
          align-items: center;
          gap: 1.5rem;
          transition: all 0.3s ease;
        }

        .stat-card:hover {
          background: rgba(255, 255, 255, 0.15);
          transform: translateY(-2px);
        }

        .stat-icon {
          font-size: 3rem;
          opacity: 0.8;
        }

        .stat-content {
          flex: 1;
        }

        .stat-number {
          font-size: 2.5rem;
          font-weight: 700;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
          line-height: 1;
        }

        .stat-label {
          font-size: 1rem;
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
        }

        .activity-section {
          margin-top: 2rem;
        }

        .section-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 1.5rem;
          font-weight: 600;
          margin: 0 0 2rem 0;
          color: #ffffff;
        }

        .section-icon {
          font-size: 1.25rem;
        }

        .activity-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
          gap: 2rem;
        }

        .activity-card {
          background: rgba(255, 255, 255, 0.1);
          backdrop-filter: blur(10px);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 12px;
          padding: 1.5rem;
        }

        .activity-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 1.5rem 0;
          color: #ffffff;
        }

        .activity-icon {
          font-size: 1rem;
        }

        .activity-list {
          display: flex;
          flex-direction: column;
          gap: 1rem;
        }

        .activity-item {
          display: flex;
          gap: 1rem;
          align-items: center;
          padding: 0.75rem;
          background: rgba(255, 255, 255, 0.05);
          border-radius: 8px;
          transition: all 0.3s ease;
        }

        .activity-item:hover {
          background: rgba(255, 255, 255, 0.1);
          transform: translateX(4px);
        }

        .item-thumbnail {
          position: relative;
          width: 60px;
          height: 80px;
          border-radius: 6px;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .item-thumbnail img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .progress-bar {
          position: absolute;
          bottom: 0;
          left: 0;
          right: 0;
          height: 3px;
          background: rgba(0, 0, 0, 0.5);
        }

        .progress-fill {
          height: 100%;
          background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
          transition: width 0.3s ease;
        }

        .item-details {
          flex: 1;
          min-width: 0;
        }

        .item-title {
          font-size: 1rem;
          font-weight: 600;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }

        .item-meta {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
          margin: 0;
        }

        .empty-state {
          text-align: center;
          padding: 2rem;
          color: rgba(255, 255, 255, 0.6);
        }

        .empty-state p {
          margin: 0;
          font-style: italic;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
          .dashboard-title {
            font-size: 1.5rem;
          }

          .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
          }

          .stat-card {
            padding: 1.5rem;
            gap: 1rem;
          }

          .stat-icon {
            font-size: 2.5rem;
          }

          .stat-number {
            font-size: 2rem;
          }

          .activity-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
          }

          .activity-card {
            padding: 1rem;
          }

          .activity-item {
            gap: 0.75rem;
          }

          .item-thumbnail {
            width: 50px;
            height: 65px;
          }
        }
      `})]})},Mh=({user:e,onUpdate:t})=>{const[n,r]=O.useState(!1),[i,o]=O.useState({name:(e==null?void 0:e.name)||"",email:(e==null?void 0:e.email)||"",current_password:"",new_password:"",confirm_password:""}),[s,l]=O.useState(!1),[u,c]=O.useState(null),p=m=>{o(v=>({...v,[m.target.name]:m.target.value}))},g=async()=>{var m;try{if(l(!0),c(null),i.new_password){if(i.new_password!==i.confirm_password){c({type:"error",text:"New passwords do not match"});return}if(i.new_password.length<6){c({type:"error",text:"Password must be at least 6 characters"});return}if(!i.current_password){c({type:"error",text:"Current password is required to change password"});return}}const v={name:i.name,email:i.email};i.new_password&&(v.current_password=i.current_password,v.new_password=i.new_password),(m=(await Le.post("/me",v)).data)!=null&&m.success?(c({type:"success",text:"Profile updated successfully"}),r(!1),o(d=>({...d,current_password:"",new_password:"",confirm_password:""})),t()):c({type:"error",text:"Failed to update profile"})}catch(v){c({type:"error",text:v.message||"Failed to update profile"})}finally{l(!1)}},y=()=>{r(!1),o({name:(e==null?void 0:e.name)||"",email:(e==null?void 0:e.email)||"",current_password:"",new_password:"",confirm_password:""}),c(null)},S=m=>new Date(m).toLocaleDateString("en-US",{year:"numeric",month:"long",day:"numeric"});return e?a.jsxs("div",{className:"profile-tab",children:[a.jsxs("div",{className:"profile-header",children:[a.jsxs("h2",{className:"profile-title",children:[a.jsx("span",{className:"title-icon",children:"👤"}),"My Profile"]}),!n&&a.jsxs("button",{className:"edit-button",onClick:()=>r(!0),children:[a.jsx("span",{className:"edit-icon",children:"✏️"}),"Edit Profile"]})]}),u&&a.jsxs("div",{className:`message ${u.type}`,children:[u.type==="success"?"✅":"❌"," ",u.text]}),a.jsxs("div",{className:"profile-content",children:[a.jsxs("div",{className:"avatar-section",children:[a.jsxs("div",{className:"avatar-container",children:[e.avatar?a.jsx("img",{src:e.avatar,alt:e.name,className:"avatar-large"}):a.jsx("div",{className:"avatar-placeholder-large",children:e.name.charAt(0).toUpperCase()}),a.jsx("button",{className:"avatar-edit-button",title:"Change Avatar",children:"📷"})]}),a.jsxs("div",{className:"avatar-info",children:[a.jsx("h3",{children:e.name}),a.jsxs("p",{children:["Member since ",S(e.member_since)]})]})]}),a.jsxs("div",{className:"profile-form",children:[a.jsxs("h4",{className:"form-section-title",children:[a.jsx("span",{className:"section-icon",children:"📝"}),"Account Information"]}),a.jsxs("div",{className:"form-grid",children:[a.jsxs("div",{className:"form-group",children:[a.jsx("label",{htmlFor:"name",children:"Full Name"}),n?a.jsx("input",{type:"text",id:"name",name:"name",value:i.name,onChange:p,className:"form-input",placeholder:"Enter your full name"}):a.jsx("div",{className:"form-display",children:e.name})]}),a.jsxs("div",{className:"form-group",children:[a.jsx("label",{htmlFor:"email",children:"Email Address"}),n?a.jsx("input",{type:"email",id:"email",name:"email",value:i.email,onChange:p,className:"form-input",placeholder:"Enter your email"}):a.jsx("div",{className:"form-display",children:e.email})]})]}),n&&a.jsxs(a.Fragment,{children:[a.jsxs("h4",{className:"form-section-title",children:[a.jsx("span",{className:"section-icon",children:"🔒"}),"Change Password",a.jsx("span",{className:"optional-label",children:"(Optional)"})]}),a.jsxs("div",{className:"form-grid",children:[a.jsxs("div",{className:"form-group",children:[a.jsx("label",{htmlFor:"current_password",children:"Current Password"}),a.jsx("input",{type:"password",id:"current_password",name:"current_password",value:i.current_password,onChange:p,className:"form-input",placeholder:"Enter current password"})]}),a.jsxs("div",{className:"form-group",children:[a.jsx("label",{htmlFor:"new_password",children:"New Password"}),a.jsx("input",{type:"password",id:"new_password",name:"new_password",value:i.new_password,onChange:p,className:"form-input",placeholder:"Enter new password"})]}),a.jsxs("div",{className:"form-group",children:[a.jsx("label",{htmlFor:"confirm_password",children:"Confirm New Password"}),a.jsx("input",{type:"password",id:"confirm_password",name:"confirm_password",value:i.confirm_password,onChange:p,className:"form-input",placeholder:"Confirm new password"})]})]})]}),n&&a.jsxs("div",{className:"form-actions",children:[a.jsx("button",{className:"save-button",onClick:g,disabled:s,children:s?"💾 Saving...":"💾 Save Changes"}),a.jsx("button",{className:"cancel-button",onClick:y,disabled:s,children:"❌ Cancel"})]})]}),a.jsxs("div",{className:"account-stats",children:[a.jsxs("h4",{className:"form-section-title",children:[a.jsx("span",{className:"section-icon",children:"📊"}),"Account Statistics"]}),a.jsxs("div",{className:"stats-grid",children:[a.jsxs("div",{className:"stat-item",children:[a.jsx("div",{className:"stat-label",children:"User ID"}),a.jsxs("div",{className:"stat-value",children:["#",e.id]})]}),a.jsxs("div",{className:"stat-item",children:[a.jsx("div",{className:"stat-label",children:"Account Status"}),a.jsx("div",{className:"stat-value status-active",children:"✅ Active"})]}),a.jsxs("div",{className:"stat-item",children:[a.jsx("div",{className:"stat-label",children:"Account Type"}),a.jsx("div",{className:"stat-value",children:"🌟 Premium Member"})]}),a.jsxs("div",{className:"stat-item",children:[a.jsx("div",{className:"stat-label",children:"Last Login"}),a.jsx("div",{className:"stat-value",children:"Just now"})]})]})]})]}),a.jsx("style",{jsx:!0,children:`
        .profile-tab {
          height: 100%;
          overflow-y: auto;
        }

        .profile-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .profile-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .title-icon {
          font-size: 1.75rem;
        }

        .edit-button {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .edit-button:hover {
          transform: translateY(-2px);
        }

        .edit-icon {
          font-size: 1rem;
        }

        .message {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 8px;
          padding: 1rem;
          margin-bottom: 1.5rem;
          border-left: 4px solid;
        }

        .message.success {
          border-left-color: #4ade80;
          background: rgba(74, 222, 128, 0.1);
        }

        .message.error {
          border-left-color: #f87171;
          background: rgba(248, 113, 113, 0.1);
        }

        .profile-content {
          display: flex;
          flex-direction: column;
          gap: 2rem;
        }

        .avatar-section {
          display: flex;
          align-items: center;
          gap: 2rem;
          padding: 2rem;
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
        }

        .avatar-container {
          position: relative;
        }

        .avatar-large {
          width: 120px;
          height: 120px;
          border-radius: 50%;
          object-fit: cover;
          border: 4px solid rgba(255, 255, 255, 0.2);
        }

        .avatar-placeholder-large {
          width: 120px;
          height: 120px;
          border-radius: 50%;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 3rem;
          font-weight: bold;
          color: white;
          border: 4px solid rgba(255, 255, 255, 0.2);
        }

        .avatar-edit-button {
          position: absolute;
          bottom: 0;
          right: 0;
          background: rgba(255, 255, 255, 0.9);
          border: none;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          font-size: 1.2rem;
          transition: all 0.3s ease;
        }

        .avatar-edit-button:hover {
          background: white;
          transform: scale(1.1);
        }

        .avatar-info h3 {
          font-size: 1.75rem;
          font-weight: 700;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
        }

        .avatar-info p {
          font-size: 1rem;
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
        }

        .profile-form {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          padding: 2rem;
        }

        .form-section-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 1.5rem 0;
          color: #ffffff;
        }

        .section-icon {
          font-size: 1rem;
        }

        .optional-label {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
          font-weight: 400;
          margin-left: 0.5rem;
        }

        .form-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
          gap: 1.5rem;
          margin-bottom: 2rem;
        }

        .form-group {
          display: flex;
          flex-direction: column;
          gap: 0.5rem;
        }

        .form-group label {
          font-weight: 600;
          color: rgba(255, 255, 255, 0.9);
          font-size: 0.9rem;
        }

        .form-input {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 8px;
          padding: 0.75rem;
          color: #ffffff;
          font-size: 1rem;
          transition: all 0.3s ease;
        }

        .form-input:focus {
          outline: none;
          border-color: rgba(102, 126, 234, 0.8);
          background: rgba(255, 255, 255, 0.15);
        }

        .form-input::placeholder {
          color: rgba(255, 255, 255, 0.5);
        }

        .form-display {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 8px;
          padding: 0.75rem;
          color: #ffffff;
          font-size: 1rem;
        }

        .form-actions {
          display: flex;
          gap: 1rem;
          justify-content: flex-end;
          margin-top: 2rem;
          padding-top: 1.5rem;
          border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .save-button {
          background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .save-button:hover:not(:disabled) {
          transform: translateY(-2px);
        }

        .save-button:disabled {
          opacity: 0.6;
          cursor: not-allowed;
          transform: none;
        }

        .cancel-button {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          color: rgba(255, 255, 255, 0.8);
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: all 0.2s ease;
        }

        .cancel-button:hover:not(:disabled) {
          background: rgba(255, 255, 255, 0.2);
          color: #ffffff;
        }

        .cancel-button:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }

        .account-stats {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          padding: 2rem;
        }

        .stats-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 1.5rem;
        }

        .stat-item {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 8px;
          padding: 1.5rem;
          text-align: center;
        }

        .stat-label {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
          margin-bottom: 0.5rem;
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        .stat-value {
          font-size: 1.1rem;
          font-weight: 600;
          color: #ffffff;
        }

        .status-active {
          color: #4ade80 !important;
        }

        .profile-loading {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 3px solid rgba(255, 255, 255, 0.3);
          border-top: 3px solid #ffffff;
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-bottom: 1rem;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
          .profile-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .profile-title {
            font-size: 1.5rem;
          }

          .avatar-section {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
          }

          .avatar-info h3 {
            font-size: 1.5rem;
          }

          .form-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
          }

          .form-actions {
            flex-direction: column;
            gap: 0.75rem;
          }

          .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
          }

          .stat-item {
            padding: 1rem;
          }
        }
      `})]}):a.jsxs("div",{className:"profile-loading",children:[a.jsx("div",{className:"loading-spinner"}),a.jsx("p",{children:"Loading profile..."})]})},Dh=()=>{const[e,t]=O.useState(null),[n,r]=O.useState(!0),[i,o]=O.useState(null),[s,l]=O.useState(1),[u,c]=O.useState(null);O.useEffect(()=>{p()},[s]);const p=async()=>{var m;try{r(!0),o(null);const v=await Le.get(`/account/watchlist?page=${s}&per_page=12`);(m=v.data)!=null&&m.success?t(v.data.data):o("Failed to load watchlist")}catch(v){o(v.message||"Failed to load watchlist")}finally{r(!1)}},g=async m=>{var v;try{if(c(m),(v=(await Le.delete(`/account/watchlist/${m}`)).data)!=null&&v.success){if(e){const d=e.items.filter(f=>f.movie_id!==m);t({...e,items:d,total:e.total-1})}}else throw new Error("Failed to remove from watchlist")}catch(w){o(w.message||"Failed to remove from watchlist")}finally{c(null)}},y=m=>new Date(m).toLocaleDateString("en-US",{year:"numeric",month:"short",day:"numeric"}),S=()=>{if(!e||e.last_page<=1)return null;const m=[],v=5;let w=Math.max(1,s-Math.floor(v/2)),d=Math.min(e.last_page,w+v-1);d-w+1<v&&(w=Math.max(1,d-v+1));for(let f=w;f<=d;f++)m.push(f);return a.jsxs("div",{className:"pagination",children:[a.jsx("button",{className:"pagination-button",onClick:()=>l(1),disabled:s===1,children:"⏮️"}),a.jsx("button",{className:"pagination-button",onClick:()=>l(s-1),disabled:s===1,children:"⬅️"}),m.map(f=>a.jsx("button",{className:`pagination-button ${s===f?"active":""}`,onClick:()=>l(f),children:f},f)),a.jsx("button",{className:"pagination-button",onClick:()=>l(s+1),disabled:s===e.last_page,children:"➡️"}),a.jsx("button",{className:"pagination-button",onClick:()=>l(e.last_page),disabled:s===e.last_page,children:"⏭️"})]})};return n?a.jsxs("div",{className:"watchlist-loading",children:[a.jsx("div",{className:"loading-spinner"}),a.jsx("p",{children:"Loading your watchlist..."})]}):i?a.jsxs("div",{className:"watchlist-error",children:[a.jsxs("p",{children:["❌ ",i]}),a.jsx("button",{className:"retry-button",onClick:p,children:"Retry"})]}):a.jsxs("div",{className:"watchlist-tab",children:[a.jsxs("div",{className:"watchlist-header",children:[a.jsxs("h2",{className:"watchlist-title",children:[a.jsx("span",{className:"title-icon",children:"📺"}),"My Watchlist"]}),e&&a.jsx("div",{className:"watchlist-stats",children:a.jsxs("span",{className:"total-count",children:[e.total," movies"]})})]}),e&&e.items.length>0?a.jsxs(a.Fragment,{children:[a.jsx("div",{className:"watchlist-grid",children:e.items.map(m=>a.jsxs("div",{className:"watchlist-item",children:[a.jsxs("div",{className:"item-poster",children:[a.jsx("img",{src:m.thumbnail,alt:m.title,onError:v=>{v.target.style.display="none"}}),a.jsxs("div",{className:"item-overlay",children:[a.jsx("button",{className:"remove-button",onClick:()=>g(m.movie_id),disabled:u===m.movie_id,title:"Remove from Watchlist",children:u===m.movie_id?"⏳":"❌"}),a.jsx("button",{className:"play-button",title:"Play Movie",children:"▶️"})]})]}),a.jsxs("div",{className:"item-details",children:[a.jsx("h3",{className:"item-title",children:m.title}),a.jsxs("div",{className:"item-meta",children:[a.jsx("span",{className:"item-year",children:m.year}),a.jsx("span",{className:"item-type",children:m.type}),m.episode_number&&a.jsxs("span",{className:"item-episode",children:["Ep. ",m.episode_number]})]}),a.jsx("div",{className:"item-category",children:m.category}),a.jsxs("div",{className:"item-added",children:["Added ",y(m.added_at)]})]})]},m.watchlist_id))}),S()]}):a.jsxs("div",{className:"empty-watchlist",children:[a.jsx("div",{className:"empty-icon",children:"📺"}),a.jsx("h3",{children:"Your watchlist is empty"}),a.jsx("p",{children:"Movies you add to your watchlist will appear here"}),a.jsx("button",{className:"browse-button",children:"Browse Movies"})]}),a.jsx("style",{jsx:!0,children:`
        .watchlist-tab {
          height: 100%;
          overflow-y: auto;
        }

        .watchlist-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .watchlist-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .title-icon {
          font-size: 1.75rem;
        }

        .watchlist-stats {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.5rem 1rem;
          border-radius: 20px;
          border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .total-count {
          color: rgba(255, 255, 255, 0.9);
          font-weight: 600;
        }

        .watchlist-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
          gap: 1.5rem;
          margin-bottom: 2rem;
        }

        .watchlist-item {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          overflow: hidden;
          transition: all 0.3s ease;
          position: relative;
        }

        .watchlist-item:hover {
          background: rgba(255, 255, 255, 0.1);
          transform: translateY(-4px);
          border-color: rgba(255, 255, 255, 0.2);
        }

        .item-poster {
          position: relative;
          aspect-ratio: 2/3;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .item-poster img {
          width: 100%;
          height: 100%;
          object-fit: cover;
          transition: transform 0.3s ease;
        }

        .watchlist-item:hover .item-poster img {
          transform: scale(1.05);
        }

        .item-overlay {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.7);
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 1rem;
          opacity: 0;
          transition: opacity 0.3s ease;
        }

        .watchlist-item:hover .item-overlay {
          opacity: 1;
        }

        .remove-button,
        .play-button {
          background: rgba(255, 255, 255, 0.9);
          border: none;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1rem;
        }

        .remove-button {
          background: rgba(255, 107, 107, 0.9);
          color: white;
        }

        .play-button {
          background: rgba(102, 126, 234, 0.9);
          color: white;
        }

        .remove-button:hover {
          background: rgba(255, 107, 107, 1);
          transform: scale(1.1);
        }

        .play-button:hover {
          background: rgba(102, 126, 234, 1);
          transform: scale(1.1);
        }

        .remove-button:disabled {
          opacity: 0.6;
          cursor: not-allowed;
          transform: none;
        }

        .item-details {
          padding: 1rem;
        }

        .item-title {
          font-size: 1rem;
          font-weight: 600;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
          line-height: 1.3;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }

        .item-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          margin-bottom: 0.5rem;
        }

        .item-year,
        .item-type,
        .item-episode {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.25rem 0.5rem;
          border-radius: 12px;
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .item-category {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.7);
          margin-bottom: 0.5rem;
        }

        .item-added {
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.5);
        }

        .pagination {
          display: flex;
          justify-content: center;
          align-items: center;
          gap: 0.5rem;
          margin-top: 2rem;
          padding-top: 1rem;
          border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pagination-button {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          color: rgba(255, 255, 255, 0.8);
          padding: 0.5rem 0.75rem;
          border-radius: 6px;
          cursor: pointer;
          transition: all 0.3s ease;
          min-width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .pagination-button:hover:not(:disabled) {
          background: rgba(255, 255, 255, 0.2);
          color: #ffffff;
        }

        .pagination-button.active {
          background: rgba(102, 126, 234, 0.8);
          border-color: rgba(102, 126, 234, 1);
          color: #ffffff;
        }

        .pagination-button:disabled {
          opacity: 0.4;
          cursor: not-allowed;
        }

        .empty-watchlist {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          text-align: center;
          min-height: 400px;
          padding: 2rem;
        }

        .empty-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .empty-watchlist h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .empty-watchlist p {
          color: rgba(255, 255, 255, 0.7);
          margin: 0 0 2rem 0;
          font-size: 1.1rem;
        }

        .browse-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 1rem 2rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .browse-button:hover {
          transform: translateY(-2px);
        }

        .watchlist-loading,
        .watchlist-error {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 3px solid rgba(255, 255, 255, 0.3);
          border-top: 3px solid #ffffff;
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-bottom: 1rem;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }

        .retry-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          margin-top: 1rem;
          transition: transform 0.2s ease;
        }

        .retry-button:hover {
          transform: translateY(-2px);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
          .watchlist-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .watchlist-title {
            font-size: 1.5rem;
          }

          .watchlist-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
          }

          .item-details {
            padding: 0.75rem;
          }

          .item-title {
            font-size: 0.9rem;
          }

          .pagination {
            flex-wrap: wrap;
            gap: 0.25rem;
          }

          .pagination-button {
            padding: 0.5rem;
            min-width: 35px;
            height: 35px;
            font-size: 0.9rem;
          }
        }
      `})]})},Uh=()=>{const[e,t]=O.useState(null),[n,r]=O.useState(!0),[i,o]=O.useState(null),[s,l]=O.useState(1),[u,c]=O.useState("all");O.useEffect(()=>{p()},[s,u]);const p=async()=>{var w;try{r(!0),o(null);let d=`/watch-history?page=${s}&per_page=15`;u!=="all"&&(d+=`&status=${u}`);const f=await Le.get(d);(w=f.data)!=null&&w.success?t(f.data.data):o("Failed to load watch history")}catch(d){o(d.message||"Failed to load watch history")}finally{r(!1)}},g=w=>{const d=new Date(w),h=Math.floor((new Date().getTime()-d.getTime())/1e3);return h<60?"Just now":h<3600?`${Math.floor(h/60)}m ago`:h<86400?`${Math.floor(h/3600)}h ago`:h<2592e3?`${Math.floor(h/86400)}d ago`:d.toLocaleDateString("en-US",{year:"numeric",month:"short",day:"numeric"})},y=w=>{const d=Math.floor(w/3600),f=Math.floor(w%3600/60);return d>0?`${d}h ${f}m`:`${f}m`},S=w=>w>=90?"#4ade80":w>=10?"#fbbf24":"#94a3b8",m=w=>w>=90?"Completed":w>=10?"In Progress":"Started",v=()=>{if(!e||e.last_page<=1)return null;const w=[],d=5;let f=Math.max(1,s-Math.floor(d/2)),h=Math.min(e.last_page,f+d-1);h-f+1<d&&(f=Math.max(1,h-d+1));for(let k=f;k<=h;k++)w.push(k);return a.jsxs("div",{className:"pagination",children:[a.jsx("button",{className:"pagination-button",onClick:()=>l(1),disabled:s===1,children:"⏮️"}),a.jsx("button",{className:"pagination-button",onClick:()=>l(s-1),disabled:s===1,children:"⬅️"}),w.map(k=>a.jsx("button",{className:`pagination-button ${s===k?"active":""}`,onClick:()=>l(k),children:k},k)),a.jsx("button",{className:"pagination-button",onClick:()=>l(s+1),disabled:s===e.last_page,children:"➡️"}),a.jsx("button",{className:"pagination-button",onClick:()=>l(e.last_page),disabled:s===e.last_page,children:"⏭️"})]})};return n?a.jsxs("div",{className:"history-loading",children:[a.jsx("div",{className:"loading-spinner"}),a.jsx("p",{children:"Loading your watch history..."})]}):i?a.jsxs("div",{className:"history-error",children:[a.jsxs("p",{children:["❌ ",i]}),a.jsx("button",{className:"retry-button",onClick:p,children:"Retry"})]}):a.jsxs("div",{className:"history-tab",children:[a.jsxs("div",{className:"history-header",children:[a.jsxs("h2",{className:"history-title",children:[a.jsx("span",{className:"title-icon",children:"🕒"}),"Watch History"]}),a.jsxs("div",{className:"history-controls",children:[a.jsxs("div",{className:"filter-buttons",children:[a.jsx("button",{className:`filter-button ${u==="all"?"active":""}`,onClick:()=>c("all"),children:"All"}),a.jsx("button",{className:`filter-button ${u==="in-progress"?"active":""}`,onClick:()=>c("in-progress"),children:"In Progress"}),a.jsx("button",{className:`filter-button ${u==="completed"?"active":""}`,onClick:()=>c("completed"),children:"Completed"})]}),e&&a.jsx("div",{className:"history-stats",children:a.jsxs("span",{className:"total-count",children:[e.total," items"]})})]})]}),e&&e.items.length>0?a.jsxs(a.Fragment,{children:[a.jsx("div",{className:"history-list",children:e.items.map(w=>a.jsxs("div",{className:"history-item",children:[a.jsxs("div",{className:"item-poster",children:[a.jsx("img",{src:w.movie_thumbnail,alt:w.movie_title,onError:d=>{d.target.style.display="none"}}),a.jsx("div",{className:"progress-overlay",children:a.jsx("div",{className:"progress-bar",style:{width:`${Math.min(Math.max(w.percentage,0),100)}%`,backgroundColor:S(w.percentage)}})}),a.jsx("div",{className:"play-overlay",children:a.jsx("button",{className:"play-button",children:"▶️"})})]}),a.jsxs("div",{className:"item-content",children:[a.jsxs("div",{className:"item-main",children:[a.jsx("h3",{className:"item-title",children:w.movie_title}),a.jsxs("div",{className:"item-meta",children:[a.jsx("span",{className:"item-year",children:w.movie_year}),a.jsx("span",{className:"item-type",children:w.movie_type}),a.jsx("span",{className:"item-category",children:w.movie_category}),w.episode_number&&a.jsxs("span",{className:"item-episode",children:["Ep. ",w.episode_number]})]}),a.jsxs("div",{className:"progress-info",children:[a.jsxs("div",{className:"progress-text",children:[a.jsx("span",{className:"status-badge",style:{backgroundColor:S(w.percentage)},children:m(w.percentage)}),a.jsxs("span",{className:"progress-percentage",children:[Math.round(w.percentage),"% watched"]})]}),a.jsxs("div",{className:"time-info",children:[w.progress>0&&a.jsxs("span",{className:"time-watched",children:[y(w.progress)," watched"]}),w.max_progress>0&&a.jsxs("span",{className:"total-duration",children:["of ",y(w.max_progress)]})]})]})]}),a.jsxs("div",{className:"item-sidebar",children:[a.jsxs("div",{className:"watch-info",children:[a.jsxs("div",{className:"last-watched",children:[a.jsx("span",{className:"watch-label",children:"Last watched"}),a.jsx("span",{className:"watch-time",children:g(w.last_watched_at)})]}),a.jsxs("div",{className:"device-info",children:[a.jsx("span",{className:"device-label",children:"Device"}),a.jsxs("span",{className:"device-name",children:["📱 ",w.device||"Unknown"," • ",w.platform||"Web"]})]})]}),a.jsxs("div",{className:"item-actions",children:[a.jsx("button",{className:"continue-button",title:"Continue Watching",children:"▶️ Continue"}),a.jsx("button",{className:"remove-button",title:"Remove from History",children:"🗑️"})]})]})]})]},w.id))}),v()]}):a.jsxs("div",{className:"empty-history",children:[a.jsx("div",{className:"empty-icon",children:"🕒"}),a.jsx("h3",{children:"No watch history yet"}),a.jsx("p",{children:"Movies you watch will appear here with your progress"}),a.jsx("button",{className:"browse-button",children:"Browse Movies"})]}),a.jsx("style",{jsx:!0,children:`
        .history-tab {
          height: 100%;
          overflow-y: auto;
        }

        .history-header {
          display: flex;
          justify-content: space-between;
          align-items: flex-start;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
          gap: 1rem;
        }

        .history-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .title-icon {
          font-size: 1.75rem;
        }

        .history-controls {
          display: flex;
          flex-direction: column;
          align-items: flex-end;
          gap: 1rem;
        }

        .filter-buttons {
          display: flex;
          gap: 0.5rem;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 8px;
          padding: 0.25rem;
        }

        .filter-button {
          background: transparent;
          border: none;
          color: rgba(255, 255, 255, 0.7);
          padding: 0.5rem 1rem;
          border-radius: 6px;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 0.9rem;
          font-weight: 500;
        }

        .filter-button:hover {
          color: #ffffff;
          background: rgba(255, 255, 255, 0.1);
        }

        .filter-button.active {
          background: rgba(102, 126, 234, 0.8);
          color: #ffffff;
          font-weight: 600;
        }

        .history-stats {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.5rem 1rem;
          border-radius: 20px;
          border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .total-count {
          color: rgba(255, 255, 255, 0.9);
          font-weight: 600;
          font-size: 0.9rem;
        }

        .history-list {
          display: flex;
          flex-direction: column;
          gap: 1.5rem;
          margin-bottom: 2rem;
        }

        .history-item {
          display: flex;
          gap: 1.5rem;
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          padding: 1.5rem;
          transition: all 0.3s ease;
        }

        .history-item:hover {
          background: rgba(255, 255, 255, 0.1);
          border-color: rgba(255, 255, 255, 0.2);
          transform: translateY(-2px);
        }

        .item-poster {
          position: relative;
          width: 120px;
          height: 160px;
          border-radius: 8px;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
          flex-shrink: 0;
        }

        .item-poster img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .progress-overlay {
          position: absolute;
          bottom: 0;
          left: 0;
          right: 0;
          height: 4px;
          background: rgba(0, 0, 0, 0.5);
        }

        .progress-bar {
          height: 100%;
          transition: width 0.3s ease;
        }

        .play-overlay {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.7);
          display: flex;
          align-items: center;
          justify-content: center;
          opacity: 0;
          transition: opacity 0.3s ease;
        }

        .history-item:hover .play-overlay {
          opacity: 1;
        }

        .play-button {
          background: rgba(102, 126, 234, 0.9);
          border: none;
          border-radius: 50%;
          width: 50px;
          height: 50px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1.25rem;
          color: white;
        }

        .play-button:hover {
          background: rgba(102, 126, 234, 1);
          transform: scale(1.1);
        }

        .item-content {
          flex: 1;
          display: flex;
          gap: 2rem;
        }

        .item-main {
          flex: 1;
          min-width: 0;
        }

        .item-title {
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 0.75rem 0;
          color: #ffffff;
          line-height: 1.3;
        }

        .item-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          margin-bottom: 1rem;
        }

        .item-year,
        .item-type,
        .item-category,
        .item-episode {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.25rem 0.5rem;
          border-radius: 12px;
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .progress-info {
          display: flex;
          flex-direction: column;
          gap: 0.5rem;
        }

        .progress-text {
          display: flex;
          align-items: center;
          gap: 1rem;
        }

        .status-badge {
          padding: 0.25rem 0.75rem;
          border-radius: 12px;
          font-size: 0.75rem;
          font-weight: 600;
          color: white;
        }

        .progress-percentage {
          font-size: 0.9rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .time-info {
          display: flex;
          gap: 0.5rem;
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
        }

        .item-sidebar {
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          align-items: flex-end;
          min-width: 200px;
        }

        .watch-info {
          text-align: right;
        }

        .last-watched,
        .device-info {
          margin-bottom: 0.75rem;
        }

        .watch-label,
        .device-label {
          display: block;
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.5);
          text-transform: uppercase;
          letter-spacing: 0.5px;
          margin-bottom: 0.25rem;
        }

        .watch-time,
        .device-name {
          display: block;
          font-size: 0.9rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .item-actions {
          display: flex;
          gap: 0.5rem;
        }

        .continue-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.5rem 1rem;
          border-radius: 6px;
          cursor: pointer;
          font-size: 0.85rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .continue-button:hover {
          transform: translateY(-1px);
        }

        .remove-button {
          background: rgba(255, 107, 107, 0.8);
          border: none;
          color: white;
          padding: 0.5rem;
          border-radius: 6px;
          cursor: pointer;
          font-size: 0.85rem;
          transition: all 0.2s ease;
        }

        .remove-button:hover {
          background: rgba(255, 107, 107, 1);
          transform: translateY(-1px);
        }

        .pagination {
          display: flex;
          justify-content: center;
          align-items: center;
          gap: 0.5rem;
          margin-top: 2rem;
          padding-top: 1rem;
          border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pagination-button {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          color: rgba(255, 255, 255, 0.8);
          padding: 0.5rem 0.75rem;
          border-radius: 6px;
          cursor: pointer;
          transition: all 0.3s ease;
          min-width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .pagination-button:hover:not(:disabled) {
          background: rgba(255, 255, 255, 0.2);
          color: #ffffff;
        }

        .pagination-button.active {
          background: rgba(102, 126, 234, 0.8);
          border-color: rgba(102, 126, 234, 1);
          color: #ffffff;
        }

        .pagination-button:disabled {
          opacity: 0.4;
          cursor: not-allowed;
        }

        .empty-history {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          text-align: center;
          min-height: 400px;
          padding: 2rem;
        }

        .empty-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .empty-history h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .empty-history p {
          color: rgba(255, 255, 255, 0.7);
          margin: 0 0 2rem 0;
          font-size: 1.1rem;
        }

        .browse-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 1rem 2rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .browse-button:hover {
          transform: translateY(-2px);
        }

        .history-loading,
        .history-error {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 3px solid rgba(255, 255, 255, 0.3);
          border-top: 3px solid #ffffff;
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-bottom: 1rem;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }

        .retry-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          margin-top: 1rem;
          transition: transform 0.2s ease;
        }

        .retry-button:hover {
          transform: translateY(-2px);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
          .history-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .history-title {
            font-size: 1.5rem;
          }

          .history-controls {
            align-items: flex-start;
            width: 100%;
          }

          .filter-buttons {
            width: 100%;
            justify-content: space-between;
          }

          .history-item {
            flex-direction: column;
            gap: 1rem;
          }

          .item-poster {
            width: 100px;
            height: 130px;
            align-self: flex-start;
          }

          .item-content {
            flex-direction: column;
            gap: 1rem;
          }

          .item-sidebar {
            align-items: flex-start;
            min-width: auto;
          }

          .watch-info {
            text-align: left;
          }

          .item-actions {
            justify-content: flex-start;
          }
        }
      `})]})},Ih=()=>{const[e,t]=O.useState([]),[n,r]=O.useState(!0),[i,o]=O.useState(null);O.useEffect(()=>{s()},[]);const s=async()=>{var u;try{r(!0);const c=await Le.get("/account/likes");(u=c.data)!=null&&u.success?t(c.data.data.items):o("Failed to load liked movies")}catch(c){o(c.message||"Failed to load liked movies")}finally{r(!1)}},l=async u=>{var c;try{(c=(await Le.post("/account/likes/toggle",{movie_id:u})).data)!=null&&c.success&&t(g=>g.filter(y=>y.movie_id!==u))}catch(p){console.error("Failed to unlike movie:",p)}};return n?a.jsxs("div",{className:"likes-loading",children:[a.jsx("div",{className:"loading-spinner"}),a.jsx("p",{children:"Loading your liked movies..."})]}):a.jsxs("div",{className:"likes-tab",children:[a.jsxs("div",{className:"likes-header",children:[a.jsxs("h2",{className:"likes-title",children:[a.jsx("span",{className:"title-icon",children:"❤️"}),"Liked Movies"]}),a.jsx("div",{className:"likes-stats",children:a.jsxs("span",{className:"total-count",children:[e.length," movies"]})})]}),e.length>0?a.jsx("div",{className:"likes-grid",children:e.map(u=>a.jsxs("div",{className:"like-item",children:[a.jsxs("div",{className:"item-poster",children:[a.jsx("img",{src:u.thumbnail,alt:u.title}),a.jsxs("div",{className:"item-overlay",children:[a.jsx("button",{className:"unlike-button",onClick:()=>l(u.movie_id),title:"Unlike",children:"💔"}),a.jsx("button",{className:"play-button",title:"Play",children:"▶️"})]})]}),a.jsxs("div",{className:"item-details",children:[a.jsx("h3",{className:"item-title",children:u.title}),a.jsxs("div",{className:"item-meta",children:[a.jsx("span",{className:"item-year",children:u.year}),a.jsx("span",{className:"item-type",children:u.type})]})]})]},u.like_id))}):a.jsxs("div",{className:"empty-likes",children:[a.jsx("div",{className:"empty-icon",children:"❤️"}),a.jsx("h3",{children:"No liked movies yet"}),a.jsx("p",{children:"Movies you like will appear here"})]}),a.jsx("style",{jsx:!0,children:`
        .likes-tab {
          height: 100%;
          overflow-y: auto;
        }

        .likes-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .likes-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .title-icon {
          font-size: 1.75rem;
        }

        .likes-stats {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.5rem 1rem;
          border-radius: 20px;
          border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .total-count {
          color: rgba(255, 255, 255, 0.9);
          font-weight: 600;
        }

        .likes-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
          gap: 1.5rem;
        }

        .like-item {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          overflow: hidden;
          transition: all 0.3s ease;
        }

        .like-item:hover {
          background: rgba(255, 255, 255, 0.1);
          transform: translateY(-4px);
        }

        .item-poster {
          position: relative;
          aspect-ratio: 2/3;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
        }

        .item-poster img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .item-overlay {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.7);
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 1rem;
          opacity: 0;
          transition: opacity 0.3s ease;
        }

        .like-item:hover .item-overlay {
          opacity: 1;
        }

        .unlike-button,
        .play-button {
          background: rgba(255, 255, 255, 0.9);
          border: none;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1rem;
        }

        .unlike-button {
          background: rgba(255, 107, 107, 0.9);
          color: white;
        }

        .play-button {
          background: rgba(102, 126, 234, 0.9);
          color: white;
        }

        .unlike-button:hover {
          background: rgba(255, 107, 107, 1);
          transform: scale(1.1);
        }

        .play-button:hover {
          background: rgba(102, 126, 234, 1);
          transform: scale(1.1);
        }

        .item-details {
          padding: 1rem;
        }

        .item-title {
          font-size: 1rem;
          font-weight: 600;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
          line-height: 1.3;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }

        .item-meta {
          display: flex;
          gap: 0.5rem;
        }

        .item-year,
        .item-type {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.25rem 0.5rem;
          border-radius: 12px;
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .empty-likes {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          text-align: center;
          min-height: 400px;
          padding: 2rem;
        }

        .empty-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .empty-likes h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .empty-likes p {
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
          font-size: 1.1rem;
        }

        .likes-loading {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 3px solid rgba(255, 255, 255, 0.3);
          border-top: 3px solid #ffffff;
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-bottom: 1rem;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
          .likes-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .likes-title {
            font-size: 1.5rem;
          }

          .likes-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
          }
        }
      `})]})},$h=()=>{const[e,t]=O.useState([]),[n,r]=O.useState(!0);O.useEffect(()=>{i()},[]);const i=async()=>{var o,s;try{r(!0);const l=await Le.get("/api/product");(o=l.data)!=null&&o.success&&t(((s=l.data.data)==null?void 0:s.data)||[])}catch(l){console.error("Failed to load products:",l)}finally{r(!1)}};return n?a.jsxs("div",{className:"products-loading",children:[a.jsx("div",{className:"loading-spinner"}),a.jsx("p",{children:"Loading your products..."})]}):a.jsxs("div",{className:"products-tab",children:[a.jsxs("div",{className:"products-header",children:[a.jsxs("h2",{className:"products-title",children:[a.jsx("span",{className:"title-icon",children:"🛍️"}),"My Products"]}),a.jsxs("button",{className:"add-product-button",children:[a.jsx("span",{className:"add-icon",children:"➕"}),"Add Product"]})]}),e.length>0?a.jsx("div",{className:"products-grid",children:e.map(o=>a.jsxs("div",{className:"product-item",children:[a.jsx("div",{className:"product-image",children:o.thumbnail?a.jsx("img",{src:o.thumbnail,alt:o.name}):a.jsx("div",{className:"product-placeholder",children:"📦"})}),a.jsxs("div",{className:"product-details",children:[a.jsx("h3",{className:"product-name",children:o.name}),a.jsx("p",{className:"product-description",children:o.description}),a.jsxs("div",{className:"product-price",children:["$",o.price]}),a.jsxs("div",{className:"product-actions",children:[a.jsx("button",{className:"edit-button",children:"✏️ Edit"}),a.jsx("button",{className:"delete-button",children:"🗑️ Delete"})]})]})]},o.id))}):a.jsxs("div",{className:"empty-products",children:[a.jsx("div",{className:"empty-icon",children:"🛍️"}),a.jsx("h3",{children:"No products yet"}),a.jsx("p",{children:"Create your first product to start selling"}),a.jsx("button",{className:"create-product-button",children:"Create Product"})]}),a.jsx("style",{jsx:!0,children:`
        .products-tab {
          height: 100%;
          overflow-y: auto;
        }

        .products-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .products-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .title-icon {
          font-size: 1.75rem;
        }

        .add-product-button {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .add-product-button:hover {
          transform: translateY(-2px);
        }

        .add-icon {
          font-size: 1rem;
        }

        .products-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
          gap: 1.5rem;
        }

        .product-item {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          overflow: hidden;
          transition: all 0.3s ease;
        }

        .product-item:hover {
          background: rgba(255, 255, 255, 0.1);
          transform: translateY(-4px);
        }

        .product-image {
          aspect-ratio: 16/9;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .product-image img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .product-placeholder {
          font-size: 3rem;
          opacity: 0.5;
        }

        .product-details {
          padding: 1.5rem;
        }

        .product-name {
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
        }

        .product-description {
          color: rgba(255, 255, 255, 0.7);
          margin: 0 0 1rem 0;
          font-size: 0.9rem;
          line-height: 1.4;
        }

        .product-price {
          font-size: 1.5rem;
          font-weight: 700;
          color: #4ade80;
          margin-bottom: 1rem;
        }

        .product-actions {
          display: flex;
          gap: 0.5rem;
        }

        .edit-button {
          background: rgba(102, 126, 234, 0.8);
          border: none;
          color: white;
          padding: 0.5rem 1rem;
          border-radius: 6px;
          cursor: pointer;
          font-size: 0.85rem;
          transition: all 0.2s ease;
        }

        .edit-button:hover {
          background: rgba(102, 126, 234, 1);
        }

        .delete-button {
          background: rgba(255, 107, 107, 0.8);
          border: none;
          color: white;
          padding: 0.5rem 1rem;
          border-radius: 6px;
          cursor: pointer;
          font-size: 0.85rem;
          transition: all 0.2s ease;
        }

        .delete-button:hover {
          background: rgba(255, 107, 107, 1);
        }

        .empty-products {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          text-align: center;
          min-height: 400px;
          padding: 2rem;
        }

        .empty-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .empty-products h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .empty-products p {
          color: rgba(255, 255, 255, 0.7);
          margin: 0 0 2rem 0;
          font-size: 1.1rem;
        }

        .create-product-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 1rem 2rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .create-product-button:hover {
          transform: translateY(-2px);
        }

        .products-loading {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 3px solid rgba(255, 255, 255, 0.3);
          border-top: 3px solid #ffffff;
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-bottom: 1rem;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
          .products-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .products-title {
            font-size: 1.5rem;
          }

          .products-grid {
            grid-template-columns: 1fr;
          }
        }
      `})]})},Bh=()=>{const[e,t]=O.useState([]),[n,r]=O.useState(!0),[i,o]=O.useState(null),[s,l]=O.useState([]),[u,c]=O.useState(""),[p,g]=O.useState(!1);O.useEffect(()=>{y()},[]);const y=async()=>{var d;try{r(!0);const f=await Le.get("/chat-heads");(d=f.data)!=null&&d.success&&t(f.data.data||[])}catch(f){console.error("Failed to load chat heads:",f)}finally{r(!1)}},S=async d=>{var f;try{const h=await Le.get(`/chat-messages?chat_head_id=${d}`);(f=h.data)!=null&&f.success&&(l(h.data.data||[]),await Le.post("/chat-mark-as-read",{chat_head_id:d}))}catch(h){console.error("Failed to load messages:",h)}},m=async()=>{var d;if(!(!u.trim()||!i||p))try{g(!0);const f=await Le.post("/chat-send",{chat_head_id:i.id,message:u.trim()});(d=f.data)!=null&&d.success&&(l(h=>[...h,f.data.data]),c(""),y())}catch(f){console.error("Failed to send message:",f)}finally{g(!1)}},v=d=>{o(d),S(d.id)},w=d=>{const f=new Date(d),k=Math.floor((new Date().getTime()-f.getTime())/1e3);return k<60?"Just now":k<3600?`${Math.floor(k/60)}m ago`:k<86400?`${Math.floor(k/3600)}h ago`:f.toLocaleDateString("en-US",{month:"short",day:"numeric",hour:"2-digit",minute:"2-digit"})};return n?a.jsxs("div",{className:"chats-loading",children:[a.jsx("div",{className:"loading-spinner"}),a.jsx("p",{children:"Loading your chats..."})]}):a.jsxs("div",{className:"chats-tab",children:[a.jsxs("div",{className:"chats-header",children:[a.jsxs("h2",{className:"chats-title",children:[a.jsx("span",{className:"title-icon",children:"💬"}),"Messages"]}),a.jsxs("button",{className:"new-chat-button",children:[a.jsx("span",{className:"new-icon",children:"✉️"}),"New Chat"]})]}),a.jsxs("div",{className:"chats-container",children:[a.jsx("div",{className:"chat-list",children:e.length>0?e.map(d=>a.jsxs("div",{className:`chat-item ${(i==null?void 0:i.id)===d.id?"active":""}`,onClick:()=>v(d),children:[a.jsxs("div",{className:"chat-avatar",children:[d.avatar?a.jsx("img",{src:d.avatar,alt:d.name}):a.jsx("div",{className:"avatar-placeholder",children:d.name.charAt(0).toUpperCase()}),d.status==="online"&&a.jsx("div",{className:"status-indicator"})]}),a.jsxs("div",{className:"chat-info",children:[a.jsxs("div",{className:"chat-header-row",children:[a.jsx("h4",{className:"chat-name",children:d.name}),d.last_message_time&&a.jsx("span",{className:"chat-time",children:w(d.last_message_time)})]}),a.jsxs("div",{className:"chat-preview-row",children:[a.jsx("p",{className:"last-message",children:d.last_message||"No messages yet"}),d.unread_count&&d.unread_count>0&&a.jsx("span",{className:"unread-badge",children:d.unread_count})]})]})]},d.id)):a.jsxs("div",{className:"empty-chat-list",children:[a.jsx("div",{className:"empty-icon",children:"💬"}),a.jsx("p",{children:"No conversations yet"})]})}),a.jsx("div",{className:"chat-messages",children:i?a.jsxs(a.Fragment,{children:[a.jsx("div",{className:"chat-messages-header",children:a.jsxs("div",{className:"chat-user-info",children:[a.jsx("div",{className:"chat-user-avatar",children:i.avatar?a.jsx("img",{src:i.avatar,alt:i.name}):a.jsx("div",{className:"avatar-placeholder",children:i.name.charAt(0).toUpperCase()})}),a.jsxs("div",{className:"chat-user-details",children:[a.jsx("h3",{children:i.name}),a.jsx("p",{children:i.status||"Unknown"})]})]})}),a.jsx("div",{className:"messages-container",children:s.length>0?s.map((d,f)=>a.jsx("div",{className:`message ${d.is_me?"sent":"received"}`,children:a.jsxs("div",{className:"message-content",children:[a.jsx("p",{children:d.message}),a.jsx("span",{className:"message-time",children:w(d.created_at)})]})},f)):a.jsx("div",{className:"no-messages",children:a.jsxs("p",{children:["Start a conversation with ",i.name]})})}),a.jsx("div",{className:"message-input-container",children:a.jsxs("div",{className:"message-input-row",children:[a.jsx("input",{type:"text",value:u,onChange:d=>c(d.target.value),placeholder:`Message ${i.name}...`,className:"message-input",onKeyPress:d=>d.key==="Enter"&&m(),disabled:p}),a.jsx("button",{className:"send-button",onClick:m,disabled:!u.trim()||p,children:p?"⏳":"📤"})]})})]}):a.jsxs("div",{className:"no-chat-selected",children:[a.jsx("div",{className:"no-chat-icon",children:"💬"}),a.jsx("h3",{children:"Select a conversation"}),a.jsx("p",{children:"Choose a chat from the list to start messaging"})]})})]}),a.jsx("style",{jsx:!0,children:`
        .chats-tab {
          height: 100%;
          display: flex;
          flex-direction: column;
        }

        .chats-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 1.5rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .chats-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .title-icon {
          font-size: 1.75rem;
        }

        .new-chat-button {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .new-chat-button:hover {
          transform: translateY(-2px);
        }

        .new-icon {
          font-size: 1rem;
        }

        .chats-container {
          display: flex;
          flex: 1;
          gap: 1.5rem;
          min-height: 0;
        }

        .chat-list {
          width: 350px;
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          overflow-y: auto;
          flex-shrink: 0;
        }

        .chat-item {
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.05);
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .chat-item:hover {
          background: rgba(255, 255, 255, 0.1);
        }

        .chat-item.active {
          background: rgba(102, 126, 234, 0.2);
          border-color: rgba(102, 126, 234, 0.3);
        }

        .chat-avatar {
          position: relative;
          width: 50px;
          height: 50px;
          flex-shrink: 0;
        }

        .chat-avatar img {
          width: 100%;
          height: 100%;
          border-radius: 50%;
          object-fit: cover;
        }

        .avatar-placeholder {
          width: 100%;
          height: 100%;
          border-radius: 50%;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 1.25rem;
          font-weight: bold;
          color: white;
        }

        .status-indicator {
          position: absolute;
          bottom: 2px;
          right: 2px;
          width: 12px;
          height: 12px;
          background: #4ade80;
          border: 2px solid rgba(255, 255, 255, 0.2);
          border-radius: 50%;
        }

        .chat-info {
          flex: 1;
          min-width: 0;
        }

        .chat-header-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 0.25rem;
        }

        .chat-name {
          font-size: 1rem;
          font-weight: 600;
          margin: 0;
          color: #ffffff;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }

        .chat-time {
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.6);
          flex-shrink: 0;
        }

        .chat-preview-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        .last-message {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          flex: 1;
        }

        .unread-badge {
          background: #ff6b6b;
          color: white;
          border-radius: 12px;
          padding: 0.2rem 0.5rem;
          font-size: 0.7rem;
          font-weight: 600;
          min-width: 18px;
          text-align: center;
        }

        .empty-chat-list {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 3rem 1rem;
          text-align: center;
        }

        .empty-icon {
          font-size: 3rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .empty-chat-list p {
          color: rgba(255, 255, 255, 0.6);
          margin: 0;
        }

        .chat-messages {
          flex: 1;
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          display: flex;
          flex-direction: column;
          min-height: 0;
        }

        .chat-messages-header {
          padding: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .chat-user-info {
          display: flex;
          align-items: center;
          gap: 1rem;
        }

        .chat-user-avatar {
          width: 40px;
          height: 40px;
        }

        .chat-user-avatar img {
          width: 100%;
          height: 100%;
          border-radius: 50%;
          object-fit: cover;
        }

        .chat-user-details h3 {
          font-size: 1.1rem;
          font-weight: 600;
          margin: 0;
          color: #ffffff;
        }

        .chat-user-details p {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
          margin: 0;
        }

        .messages-container {
          flex: 1;
          overflow-y: auto;
          padding: 1rem;
          display: flex;
          flex-direction: column;
          gap: 1rem;
        }

        .message {
          display: flex;
          max-width: 70%;
        }

        .message.sent {
          align-self: flex-end;
        }

        .message.received {
          align-self: flex-start;
        }

        .message-content {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.75rem 1rem;
          border-radius: 12px;
          position: relative;
        }

        .message.sent .message-content {
          background: rgba(102, 126, 234, 0.8);
        }

        .message-content p {
          margin: 0 0 0.5rem 0;
          color: #ffffff;
          line-height: 1.4;
        }

        .message-time {
          font-size: 0.7rem;
          color: rgba(255, 255, 255, 0.6);
        }

        .no-messages {
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100%;
          text-align: center;
          color: rgba(255, 255, 255, 0.6);
        }

        .message-input-container {
          padding: 1rem;
          border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .message-input-row {
          display: flex;
          gap: 0.5rem;
        }

        .message-input {
          flex: 1;
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 20px;
          padding: 0.75rem 1rem;
          color: #ffffff;
          font-size: 1rem;
          transition: all 0.3s ease;
        }

        .message-input:focus {
          outline: none;
          border-color: rgba(102, 126, 234, 0.8);
          background: rgba(255, 255, 255, 0.15);
        }

        .message-input::placeholder {
          color: rgba(255, 255, 255, 0.5);
        }

        .send-button {
          background: rgba(102, 126, 234, 0.8);
          border: none;
          color: white;
          border-radius: 50%;
          width: 45px;
          height: 45px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.2s ease;
          font-size: 1rem;
        }

        .send-button:hover:not(:disabled) {
          background: rgba(102, 126, 234, 1);
          transform: scale(1.05);
        }

        .send-button:disabled {
          opacity: 0.5;
          cursor: not-allowed;
          transform: none;
        }

        .no-chat-selected {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 100%;
          text-align: center;
          padding: 2rem;
        }

        .no-chat-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .no-chat-selected h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .no-chat-selected p {
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
          font-size: 1.1rem;
        }

        .chats-loading {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 3px solid rgba(255, 255, 255, 0.3);
          border-top: 3px solid #ffffff;
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-bottom: 1rem;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
          .chats-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .chats-title {
            font-size: 1.5rem;
          }

          .chats-container {
            flex-direction: column;
            height: 500px;
          }

          .chat-list {
            width: 100%;
            height: 200px;
          }

          .chat-messages {
            height: 300px;
          }

          .message {
            max-width: 85%;
          }
        }
      `})]})},Rd=({initialTab:e="dashboard",onClose:t})=>{const[n,r]=O.useState(e),[i,o]=O.useState(null),[s,l]=O.useState(!0),[u,c]=O.useState(null),p=[{id:"dashboard",label:"Dashboard",icon:"📊"},{id:"profile",label:"Profile",icon:"👤"},{id:"watchlist",label:"Watchlist",icon:"📺"},{id:"history",label:"History",icon:"🕒"},{id:"likes",label:"Likes",icon:"❤️"},{id:"products",label:"Products",icon:"🛍️"},{id:"chats",label:"Chats",icon:"💬"}];O.useEffect(()=>{g()},[]);const g=async()=>{var S;try{l(!0);const m=await Le.get("/account/dashboard");(S=m.data)!=null&&S.success?o(m.data.data):c("Failed to load dashboard data")}catch(m){c(m.message||"Failed to load dashboard")}finally{l(!1)}},y=()=>{if(s)return a.jsxs("div",{className:"account-loading",children:[a.jsx("div",{className:"loading-spinner"}),a.jsx("p",{children:"Loading your account data..."})]});if(u)return a.jsxs("div",{className:"account-error",children:[a.jsxs("p",{children:["❌ ",u]}),a.jsx("button",{className:"retry-button",onClick:g,children:"Retry"})]});switch(n){case"dashboard":return a.jsx(Xa,{data:i,onRefresh:g});case"profile":return a.jsx(Mh,{user:i==null?void 0:i.user,onUpdate:g});case"watchlist":return a.jsx(Dh,{});case"history":return a.jsx(Uh,{});case"likes":return a.jsx(Ih,{});case"products":return a.jsx($h,{});case"chats":return a.jsx(Bh,{});default:return a.jsx(Xa,{data:i,onRefresh:g})}};return a.jsxs("div",{className:"account-layout",children:[a.jsx("div",{className:"account-header",children:a.jsxs("div",{className:"account-header-content",children:[a.jsxs("h1",{className:"account-title",children:[a.jsx("span",{className:"account-icon",children:"⚙️"}),"My Account"]}),t&&a.jsx("button",{className:"account-close-button",onClick:t,"aria-label":"Close Account",children:"✕"})]})}),a.jsxs("div",{className:"account-container",children:[a.jsxs("div",{className:"account-sidebar",children:[a.jsx("div",{className:"account-user-info",children:(i==null?void 0:i.user)&&a.jsxs(a.Fragment,{children:[a.jsx("div",{className:"user-avatar",children:i.user.avatar?a.jsx("img",{src:i.user.avatar,alt:i.user.name,className:"avatar-image"}):a.jsx("div",{className:"avatar-placeholder",children:i.user.name.charAt(0).toUpperCase()})}),a.jsxs("div",{className:"user-details",children:[a.jsx("h3",{className:"user-name",children:i.user.name}),a.jsx("p",{className:"user-email",children:i.user.email})]})]})}),a.jsx("nav",{className:"account-nav",children:p.map(S=>a.jsxs("button",{className:`nav-tab ${n===S.id?"active":""}`,onClick:()=>r(S.id),children:[a.jsx("span",{className:"tab-icon",children:S.icon}),a.jsx("span",{className:"tab-label",children:S.label}),S.id==="watchlist"&&(i==null?void 0:i.stats.watchlist_count)>0&&a.jsx("span",{className:"tab-badge",children:i.stats.watchlist_count}),S.id==="likes"&&(i==null?void 0:i.stats.likes_count)>0&&a.jsx("span",{className:"tab-badge",children:i.stats.likes_count})]},S.id))})]}),a.jsx("div",{className:"account-main",children:a.jsx("div",{className:"account-content",children:y()})})]}),a.jsx("style",{jsx:!0,children:`
        .account-layout {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
          color: #ffffff;
          z-index: 1000;
          overflow: hidden;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .account-header {
          background: rgba(0, 0, 0, 0.3);
          backdrop-filter: blur(10px);
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
          padding: 1rem 0;
        }

        .account-header-content {
          max-width: 1400px;
          margin: 0 auto;
          padding: 0 2rem;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        .account-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 1.75rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .account-icon {
          font-size: 1.5rem;
        }

        .account-close-button {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          color: #ffffff;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1.2rem;
        }

        .account-close-button:hover {
          background: rgba(255, 255, 255, 0.2);
          transform: scale(1.05);
        }

        .account-container {
          display: flex;
          height: calc(100vh - 80px);
          max-width: 1400px;
          margin: 0 auto;
          padding: 0 2rem;
        }

        .account-sidebar {
          width: 280px;
          background: rgba(0, 0, 0, 0.2);
          backdrop-filter: blur(10px);
          border-radius: 12px;
          margin: 1rem 0;
          padding: 2rem 1.5rem;
          border: 1px solid rgba(255, 255, 255, 0.1);
          overflow-y: auto;
        }

        .account-user-info {
          margin-bottom: 2rem;
          text-align: center;
          padding-bottom: 2rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
          margin-bottom: 1rem;
        }

        .avatar-image {
          width: 80px;
          height: 80px;
          border-radius: 50%;
          object-fit: cover;
          border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .avatar-placeholder {
          width: 80px;
          height: 80px;
          border-radius: 50%;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 2rem;
          font-weight: bold;
          color: white;
          margin: 0 auto;
          border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .user-name {
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
        }

        .user-email {
          font-size: 0.9rem;
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
        }

        .account-nav {
          display: flex;
          flex-direction: column;
          gap: 0.5rem;
        }

        .nav-tab {
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.25rem;
          background: transparent;
          border: 1px solid transparent;
          border-radius: 8px;
          color: rgba(255, 255, 255, 0.8);
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1rem;
          width: 100%;
          text-align: left;
          position: relative;
        }

        .nav-tab:hover {
          background: rgba(255, 255, 255, 0.1);
          border-color: rgba(255, 255, 255, 0.2);
          color: #ffffff;
        }

        .nav-tab.active {
          background: rgba(255, 255, 255, 0.15);
          border-color: rgba(255, 255, 255, 0.3);
          color: #ffffff;
          font-weight: 600;
        }

        .tab-icon {
          font-size: 1.25rem;
          width: 24px;
          text-align: center;
        }

        .tab-label {
          flex: 1;
        }

        .tab-badge {
          background: #ff6b6b;
          color: white;
          border-radius: 12px;
          padding: 0.25rem 0.5rem;
          font-size: 0.75rem;
          font-weight: 600;
          min-width: 20px;
          text-align: center;
        }

        .account-main {
          flex: 1;
          margin: 1rem 0 1rem 1.5rem;
          background: rgba(0, 0, 0, 0.2);
          backdrop-filter: blur(10px);
          border-radius: 12px;
          border: 1px solid rgba(255, 255, 255, 0.1);
          overflow: hidden;
        }

        .account-content {
          height: 100%;
          overflow-y: auto;
          padding: 2rem;
        }

        .account-loading {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 3px solid rgba(255, 255, 255, 0.3);
          border-top: 3px solid #ffffff;
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-bottom: 1rem;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }

        .account-error {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .retry-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          margin-top: 1rem;
          transition: transform 0.2s ease;
        }

        .retry-button:hover {
          transform: translateY(-2px);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
          .account-layout {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
          }

          .account-container {
            flex-direction: column;
            padding: 0 1rem;
          }

          .account-sidebar {
            width: 100%;
            margin: 0.5rem 0;
            padding: 1rem;
          }

          .account-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-align: left;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
          }

          .user-avatar {
            margin-bottom: 0;
          }

          .avatar-image,
          .avatar-placeholder {
            width: 50px;
            height: 50px;
            font-size: 1.25rem;
          }

          .user-name {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
          }

          .user-email {
            font-size: 0.85rem;
          }

          .account-nav {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
          }

          .nav-tab {
            padding: 0.75rem;
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
          }

          .tab-label {
            font-size: 0.9rem;
          }

          .account-main {
            margin: 0.5rem 0;
            flex: 1;
            min-height: 400px;
          }

          .account-content {
            padding: 1rem;
          }
        }
      `})]})};document.addEventListener("DOMContentLoaded",()=>{const e=document.getElementById("account-layout-root");if(e){const t=xl(e),r=new URLSearchParams(window.location.search).get("tab")||e.dataset.initialTab||"dashboard";t.render(a.jsx(Rd,{initialTab:r,onClose:()=>{window.history.back()}}))}Hh(),Vh()});function Hh(){document.querySelectorAll("[data-video-player]").forEach(t=>{console.log("Video player container found:",t)})}function Vh(){const e=document.getElementById("account-menu-trigger");e&&e.addEventListener("click",t=>{t.preventDefault(),Nl()})}function Nl(e="dashboard"){let t=document.getElementById("account-layout-root");t?t.style.display="block":(t=document.createElement("div"),t.id="account-layout-root",t.dataset.initialTab=e,document.body.appendChild(t),xl(t).render(a.jsx(Rd,{initialTab:e,onClose:()=>{t==null||t.remove()}})))}window.showAccountLayout=Nl;window.KatogoApp={showAccountLayout:Nl};

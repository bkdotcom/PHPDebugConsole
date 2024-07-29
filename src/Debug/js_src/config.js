import $ from 'jquery'
import * as http from './http.js' // cookie & query utils

var config = {
  fontAwesomeCss: '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
  clipboardSrc: '//cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js',
  iconsExpand: {
    expand: 'fa-plus-square-o',
    collapse: 'fa-minus-square-o',
    empty: 'fa-square-o'
  },
  iconsMisc: {
    '.string-encoded': '<i class="fa fa-barcode"></i>',
    '.timestamp': '<i class="fa fa-calendar"></i>'
  },
  iconsArray: {
    '> .array-inner > li > .exclude-count': '<i class="fa fa-eye-slash"></i>'
  },
  iconsObject: {
    '> .t_modifier_abstract': '<i class="fa fa-circle-o"></i>',
    '> .t_modifier_final': '<i class="fa fa-hand-stop-o"></i>',
    '> .t_modifier_interface': '<i class="fa fa-handshake-o"></i>',
    '> .t_modifier_readonly': '<span class="fa-stack">' +
      '<i class="fa fa-pencil fa-stack-1x"></i>' +
      '<i class="fa fa-ban fa-flip-horizontal fa-stack-2x text-muted"></i>' +
      '</span>',
    '> .t_modifier_trait': '<i class="fa fa-puzzle-piece"></i>',
    '> .info.magic': '<i class="fa fa-fw fa-magic"></i>',
    'parent:not(.groupByInheritance) > dd[data-inherited-from]:not(.private-ancestor)': '<i class="fa fa-fw fa-clone" title="Inherited"></i>',
    'parent:not(.groupByInheritance) > dd.private-ancestor': '<i class="fa fa-lock" title="Private ancestor"></i>',
    '> dd[data-attributes]': '<i class="fa fa-hashtag" title="Attributes"></i>',
    '> dd[data-declared-prev]': '<i class="fa fa-fw fa-repeat" title="Overrides"></i>',
    '> .method.isDeprecated': '<i class="fa fa-fw fa-arrow-down" title="Deprecated"></i>',
    '> .method > .t_modifier_abstract': '<i class="fa fa-circle-o" title="abstract method"></i>',
    '> .method > .t_modifier_magic': '<i class="fa fa-magic" title="magic method"></i>',
    '> .method > .t_modifier_final': '<i class="fa fa-hand-stop-o"></i>',
    '> .method > .parameter.isPromoted': '<i class="fa fa-arrow-up" title="Promoted"></i>',
    '> .method > .parameter[data-attributes]': '<i class="fa fa-hashtag" title="Attributes"></i>',
    '> .method[data-implements]': '<i class="fa fa-handshake-o" title="Implements"></i>',
    '> .method[data-throws]': '<i class="fa fa-flag" title="Throws"></i>',
    '> .property.debuginfo-value': '<i class="fa fa-eye" title="via __debugInfo()"></i>',
    '> .property.debuginfo-excluded': '<i class="fa fa-eye-slash" title="not included in __debugInfo"></i>',
    '> .property.isDynamic': '<i class="fa fa-warning" title="Dynamic"></i>',
    '> .property.isPromoted': '<i class="fa fa-arrow-up" title="Promoted"></i>',
    '> .property > .t_modifier_magic': '<i class="fa fa-magic" title="magic property"></i>',
    '> .property > .t_modifier_magic-read': '<i class="fa fa-magic" title="magic property"></i>',
    '> .property > .t_modifier_magic-write': '<i class="fa fa-magic" title="magic property"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=private]': '<i class="fa fa-user-secret"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=protected]': '<i class="fa fa-shield"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=debuginfo-excluded]': '<i class="fa fa-eye-slash"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=inherited]': '<i class="fa fa-clone"></i>'
  },
  // debug methods (not object methods)
  iconsMethods: {
    '.m_assert': '<i class="fa-lg"><b>&ne;</b></i>',
    '.m_clear': '<i class="fa fa-lg fa-ban"></i>',
    '.m_count': '<i class="fa fa-lg fa-plus-circle"></i>',
    '.m_countReset': '<i class="fa fa-lg fa-plus-circle"></i>',
    '.m_error': '<i class="fa fa-lg fa-times-circle"></i>',
    '.m_group.expanded': '<i class="fa fa-lg fa-minus-square-o"></i>',
    '.m_group': '<i class="fa fa-lg fa-plus-square-o"></i>',
    '.m_info': '<i class="fa fa-lg fa-info-circle"></i>',
    '.m_profile': '<i class="fa fa-lg fa-pie-chart"></i>',
    '.m_profileEnd': '<i class="fa fa-lg fa-pie-chart"></i>',
    '.m_time': '<i class="fa fa-lg fa-clock-o"></i>',
    '.m_timeLog': '<i class="fa fa-lg fa-clock-o"></i>',
    '.m_trace': '<i class="fa fa-list"></i>',
    '.m_warn': '<i class="fa fa-lg fa-warning"></i>'
  },
  debugKey: getDebugKey(),
  drawer: false,
  persistDrawer: false,
  linkFiles: false,
  linkFilesTemplate: 'subl://open?url=file://%file&line=%line',
  localStorageKey: 'phpDebugConsole',
  useLocalStorage: true,
  tooltip: true,
  cssFontAwesome5: '' +
    '.debug .fa-bell-o:before { content:"\\f0f3"; font-weight:400; }' +
    '.debug .fa-calendar:before { content:"\\f073"; }' +
    '.debug .fa-clock-o:before { content:"\\f017"; font-weight:400; }' +
    '.debug .fa-clone:before { content:"\\f24d"; font-weight:400; }' +
    '.debug .fa-envelope-o:before { content:"\\f0e0"; font-weight:400; }' +
    '.debug .fa-exchange:before { content:"\\f362"; }' +
    '.debug .fa-external-link:before { content:"\\f35d"; }' +
    '.debug .fa-eye-slash:before { content:"\\f070"; font-weight:400; }' +
    '.debug .fa-file-code-o:before { content:"\\f1c9"; font-weight:400; }' +
    '.debug .fa-file-text-o:before { content:"\\f15c"; font-weight:400; }' +
    '.debug .fa-files-o:before { content:"\\f0c5"; font-weight:400; }' +
    '.debug .fa-hand-stop-o:before { content:"\\f256"; font-weight:400; }' +
    '.debug .fa-minus-square-o:before { content:"\\f146"; font-weight:400; }' +
    '.debug .fa-pencil:before { content:"\\f303" }' +
    '.debug .fa-pie-chart:before { content:"\\f200"; }' +
    '.debug .fa-plus-square-o:before { content:"\\f0fe"; font-weight:400; }' +
    '.debug .fa-shield:before { content:"\\f3ed"; }' +
    '.debug .fa-square-o:before { content:"\\f0c8"; font-weight:400; }' +
    '.debug .fa-user-o:before { content:"\\f007"; }' +
    '.debug .fa-warning:before { content:"\\f071"; }' +
    '.debug .fa.fa-github { font-family: "Font Awesome 5 Brands"; }'
}

export function Config () {
  var storedConfig = null
  if (config.useLocalStorage) {
    storedConfig = http.lsGet(config.localStorageKey)
  }
  this.config = $.extend({}, config, storedConfig || {})
  this.haveSavedConfig = typeof storedConfig === 'object'
  this.localStorageKeys = ['persistDrawer', 'openDrawer', 'openSidebar', 'height', 'linkFiles', 'linkFilesTemplate']
}

Config.prototype.get = function (key) {
  if (typeof key === 'undefined') {
    return JSON.parse(JSON.stringify(this.config))
  }
  return typeof this.config[key] !== 'undefined'
    ? this.config[key]
    : null
}

Config.prototype.set = function (key, val) {
  var setVals = {}
  if (typeof key === 'object') {
    setVals = key
  } else {
    setVals[key] = val
  }
  // console.log('config.set', setVals)
  for (var k in setVals) {
    this.config[k] = setVals[k]
  }
  if (this.config.useLocalStorage) {
    this.updateStorage(setVals)
  }
  this.haveSavedConfig = true
}

Config.prototype.updateStorage = function (setVals) {
  var lsObj = http.lsGet(this.config.localStorageKey) || {}
  var haveLsKey = false
  var key = null
  if (setVals.linkFilesTemplateDefault && !lsObj.linkFilesTemplate) {
    // we don't have a user specified template... use the default
    this.config.linkFiles = setVals.linkFiles = true
    this.config.linkFilesTemplate = setVals.linkFilesTemplate = setVals.linkFilesTemplateDefault
  }
  for (var i = 0, count = this.localStorageKeys.length; i < count; i++) {
    key = this.localStorageKeys[i]
    if (typeof setVals[key] !== 'undefined') {
      haveLsKey = true
      lsObj[key] = setVals[key]
    }
  }
  if (haveLsKey) {
    http.lsSet(this.config.localStorageKey, lsObj)
  }
}

function getDebugKey () {
  var key = null
  var queryParams = http.queryDecode()
  var cookieValue = http.cookieGet('debug')
  if (typeof queryParams.debug !== 'undefined') {
    key = queryParams.debug
  } else if (cookieValue) {
    key = cookieValue
  }
  return key
}

import $ from 'zest'
import * as http from './http.js' // cookie & query utils
import { Dict } from './Dict.js'

var config = {
  clipboardSrc: '//cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js',
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
    '.debug .fa.fa-github { font-family: "Font Awesome 5 Brands"; }',
  debugKey: getDebugKey(),
  drawer: false,
  fontAwesomeCss: '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
  iconsArray: {
    '> .array-inner > li > .exclude-count': '<i class="fa fa-eye-slash"></i>'
  },
  iconsExpand: {
    expand: 'fa-plus-square-o',
    collapse: 'fa-minus-square-o',
    empty: 'fa-square-o'
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
  iconsMisc: {
    '.string-encoded': '<i class="fa fa-barcode"></i>',
    '.timestamp': '<i class="fa fa-calendar"></i>'
  },
  linkFiles: false,
  linkFilesTemplate: 'subl://open?url=file://%file&line=%line',
  localStorageKey: 'phpDebugConsole',
  iconsObject: {
    '> .t_modifier_abstract': '<i class="fa fa-circle-o"></i>',
    '> .t_modifier_final': '<i class="fa fa-hand-stop-o"></i>',
    '> .t_modifier_interface': '<i class="fa fa-handshake-o"></i>',
    '> .t_modifier_lazy': '👻 ',
    '> .t_modifier_readonly': '<span class="fa-stack">' +
      '<i class="fa fa-pencil fa-stack-1x"></i>' +
      '<i class="fa fa-ban fa-flip-horizontal fa-stack-2x text-muted"></i>' +
      '</span>',
    '> .t_modifier_trait': '<i class="fa fa-puzzle-piece"></i>',
    '> .info.magic': '<i class="fa fa-fw fa-magic"></i>',
    'parent:not(.groupByInheritance) > dd[data-inherited-from]:not(.private-ancestor)': '<i class="fa fa-fw fa-clone" title="{string:inherited}"></i>',
    'parent:not(.groupByInheritance) > dd.private-ancestor': '<i class="fa fa-lock" title="{string:private-ancestor}"></i>',
    '> dd[data-attributes]': '<i class="fa fa-hashtag" title="{string:attributes}"></i>',
    '> dd[data-declared-prev]': '<i class="fa fa-fw fa-repeat" title="{string:overrides}"></i>',
    '> .method.isAbstract': '<i class="fa fa-circle-o" title="{string:method.abstract}"></i>',
    '> .method.isDeprecated': '<i class="fa fa-fw fa-arrow-down" title="{string:deprecated}"></i>',
    '> .method.isFinal': '<i class="fa fa-hand-stop-o" title="{string:final}"></i>',
    '> .method > .t_modifier_magic': '<i class="fa fa-magic" title="{string:method.magic}"></i>',
    '> .method > .parameter.isPromoted': '<i class="fa fa-arrow-up" title="{string:promoted}"></i>',
    '> .method > .parameter[data-attributes]': '<i class="fa fa-hashtag" title="{string:attributes}"></i>',
    '> .method[data-implements]': '<i class="fa fa-handshake-o" title="{string:implements}"></i>',
    '> .method[data-throws]': '<i class="fa fa-flag" title="{string:throws}"></i>',
    '> .property.debuginfo-value': '<i class="fa fa-eye" title="{string:debugInfo-value}"></i>',
    '> .property.debuginfo-excluded': '<i class="fa fa-eye-slash" title="{string:debugInfo-excluded}"></i>',
    '> .property.isDynamic': '<i class="fa fa-warning" title="{string:dynamic}"></i>',
    '> .property.isPromoted': '<i class="fa fa-arrow-up" title="{string:promoted}"></i>',
    '> .property.getHook, > .property.setHook': function () {
      var title = '{string:hook.set}'
      if ($(this).hasClass('getHook') && $(this).hasClass('setHook')) {
        title = '{string:hook.both}'
      } else if ($(this).hasClass('getHook')) {
        title = '{string:hook.get}'
      }
      return $('<i class="fa">🪝</i>').prop('title', title)
    },
    '> .property.isDeprecated': '<i class="fa fa-fw fa-arrow-down" title="{string:deprecated}"></i>',
    '> .property.isVirtual': '<i class="fa fa-cloud isVirtual" title="{string:virtual}"></i>',
    '> .property.isWriteOnly': '<i class="fa fa-eye-slash" title="{string:write-only}"></i>',
    '> .property > .t_modifier_magic': '<i class="fa fa-magic" title="{string:property.magic}"></i>',
    '> .property > .t_modifier_magic-read': '<i class="fa fa-magic" title="{string:property.magic}"></i>',
    '> .property > .t_modifier_magic-write': '<i class="fa fa-magic" title="{string:property.magic}"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=private]': '<i class="fa fa-user-secret"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=protected]': '<i class="fa fa-shield"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=debuginfo-excluded]': '<i class="fa fa-eye-slash"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=inherited]': '<i class="fa fa-clone"></i>'
  },
  persistDrawer: false,
  strings: {
    attributes: 'Attributes',
    'cfg.cookie': 'Debug cookie',
    'cfg.documentation': 'Documentation',
    'cfg.link-files': 'Create file links',
    'cfg.link-template': 'Link template',
    'cfg.persist-drawer': 'Keep open/closed',
    'cfg.theme': 'Theme',
    'cfg.theme.auto': 'Auto',
    'cfg.theme.dark': 'Dark',
    'cfg.theme.light': 'Light',
    'debugInfo-excluded': 'not included in __debugInfo',
    'debugInfo-value': 'via __debugInfo()',
    deprecated: 'Deprecated',
    dynamic: 'Dynamic',

    'error.cat.deprecated': 'Deprecated',
    'error.cat.error': 'Error',
    'error.cat.fatal': 'Fatal',
    'error.cat.notice': 'Notice',
    'error.cat.strict': 'Strict',
    'error.cat.warning': 'Warning',

    final: 'Final',
    'hook.both': 'Get and set hooks',
    'hook.get': 'Get hook',
    'hook.set': 'Set hook',
    implements: 'Implements',
    inherited: 'Inherited',
    less: 'Less',
    'method.abstract': 'Abstract method',
    'method.magic': 'Magic method',
    more: 'More',
    overrides: 'Overrides',
    'object.methods.magic.1': 'This object has a {method} method', // wampClient
    'object.methods.magic.2': 'This object has {method1} and {method2} methods', // wampClient
    'object.methods.return-value': 'return value', // wampClient
    'object.methods.static-variables': 'static variables', // wampClient
    'private-ancestor': 'Private ancestor',
    promoted: 'Promoted',
    'property.magic': 'Magic property',
    'side.alert': 'Alert',
    'side.channels': 'Channels',
    'side.error': 'Error',
    'side.expand-all-groups': 'Exp All Groups',
    'side.info': 'Info',
    'side.other': 'Other',
    'side.php-errors': 'PHP Errors',
    'side.warning': 'Warning',
    throws: 'Throws',
    virtual: 'Virtual',
    'write-only': 'Write-only',
  },
  theme: 'auto',
  tooltip: true,
  useLocalStorage: true,
}

export function Config () {
  var storedConfig = null
  if (config.useLocalStorage) {
    storedConfig = http.lsGet(config.localStorageKey)
  }
  this.config = $.extend(true, {}, config, storedConfig || {})
  this.dict = new Dict(this.config.strings)
  this.haveSavedConfig = typeof storedConfig === 'object'
  this.localStorageKeys = ['persistDrawer', 'openDrawer', 'openSidebar', 'height', 'linkFiles', 'linkFilesTemplate', 'theme']
}

Config.prototype.get = function (key) {
  if (typeof key === 'undefined') {
    // unable to use JSON.parse(JSON.stringify(this.config))
    //  iconsObject functions are lost
    return deepCopy(this.config)
  }
  return typeof this.config[key] !== 'undefined'
    ? this.config[key]
    : null
}

Config.prototype.themeGet = function () {
  var theme = this.get('theme')
  if (theme === 'auto') {
    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }
  return theme
}

Config.prototype.set = function (key, val) {
  var setVals = {}
  if (typeof key === 'object') {
    setVals = key
  } else {
    setVals[key] = val
  }
  this.config = $.extend(true, {}, this.config, setVals)
  if (setVals.strings) {
    this.dict.update(setVals.strings)
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

function deepCopy (src) {
  let target = Array.isArray(src) ? [] : {}
  for (let prop in src) {
    let value = src[prop]
    target[prop] = value && typeof value === 'object'
      ? deepCopy(value)
      : value
  }
  return target
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

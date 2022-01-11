import $ from 'jquery'
import { addTest as addFilterTest, addPreFilter } from './filter.js'

var config
var options
var methods // method filters
var $root
var initialized = false
var methodLabels = {
  alert: '<i class="fa fa-fw fa-lg fa-bullhorn"></i>Alerts',
  error: '<i class="fa fa-fw fa-lg fa-times-circle"></i>Error',
  warn: '<i class="fa fa-fw fa-lg fa-warning"></i>Warning',
  info: '<i class="fa fa-fw fa-lg fa-info-circle"></i>Info',
  other: '<i class="fa fa-fw fa-lg fa-sticky-note-o"></i>Other'
}
var sidebarHtml = '' +
  '<div class="debug-sidebar show no-transition">' +
    '<div class="sidebar-toggle">' +
      '<div class="collapse">' +
        '<i class="fa fa-caret-left"></i>' +
        '<i class="fa fa-ellipsis-v"></i>' +
        '<i class="fa fa-caret-left"></i>' +
      '</div>' +
      '<div class="expand">' +
        '<i class="fa fa-caret-right"></i>' +
        '<i class="fa fa-ellipsis-v"></i>' +
        '<i class="fa fa-caret-right"></i>' +
      '</div>' +
    '</div>' +
    '<div class="sidebar-content">' +
      '<ul class="list-unstyled debug-filters">' +
        '<li class="php-errors">' +
          '<span><i class="fa fa-fw fa-lg fa-code"></i>PHP Errors</span>' +
          '<ul class="list-unstyled">' +
          '</ul>' +
        '</li>' +
        '<li class="channels">' +
          '<span><i class="fa fa-fw fa-lg fa-list-ul"></i>Channels</span>' +
          '<ul class="list-unstyled">' +
          '</ul>' +
        '</li>' +
      '</ul>' +
      '<button class="expand-all" style="display:none;"><i class="fa fa-lg fa-plus"></i> Exp All Groups</button>' +
    '</div>' +
  '</div>'

export function init ($debugRoot) {
  var $debugTabLog = $debugRoot.find('> .tab-panes > .tab-primary')

  config = $debugRoot.data('config') || $('body').data('config')
  $root = $debugRoot

  if ($debugTabLog.length && $debugTabLog.data('options').sidebar) {
    addMarkup($root)
  }

  if (config.get('persistDrawer') && !config.get('openSidebar')) {
    close($root)
  }

  $root.on('click', '.close[data-dismiss=alert]', onClickCloseAlert)
  $root.on('click', '.sidebar-toggle', onClickSidebarToggle)
  $root.on('change', '.debug-sidebar input[type=checkbox]', onChangeSidebarInput)

  if (initialized) {
    return
  }

  addPreFilter(preFilter)
  addFilterTest(filterTest)
  initialized = true
}

function onChangeSidebarInput (e) {
  var $input = $(this)
  var $toggle = $input.closest('.toggle')
  var $nested = $toggle.next('ul').find('.toggle')
  var isActive = $input.is(':checked')
  var $errorSummary = $('.m_alert.error-summary.have-fatal')
  $toggle.toggleClass('active', isActive)
  $nested.toggleClass('active', isActive)
  if ($input.val() === 'fatal') {
    $errorSummary.find('.error-fatal').toggleClass('filter-hidden', !isActive)
    $errorSummary.toggleClass('filter-hidden', $errorSummary.children().not('.filter-hidden').length === 0)
  }
}

function onClickCloseAlert () {
  // setTimeout -> new thread -> executed after event bubbled
  var $debug = $(this).closest('.debug')
  setTimeout(function () {
    if ($debug.find('.m_alert').length) {
      $debug.find('.debug-sidebar input[data-toggle=method][value=alert]').parent().addClass('disabled')
    }
  })
}

function onClickSidebarToggle () {
  var $debug = $(this).closest('.debug')
  var isVis = $debug.find('.debug-sidebar').is('.show')
  if (!isVis) {
    open($debug)
  } else {
    close($debug)
  }
}

function filterTest ($node) {
  var matches = $node[0].className.match(/\bm_(\S+)\b/)
  var method = matches ? matches[1] : null
  if (!options.sidebar) {
    return true
  }
  if (method === 'group' && $node.find('> .group-body')[0].className.match(/level-(error|info|warn)/)) {
    method = $node.find('> .group-body')[0].className.match(/level-(error|info|warn)/)[1]
    $node.toggleClass('filter-hidden-body', methods.indexOf(method) < 0)
  }
  if (['alert', 'error', 'warn', 'info'].indexOf(method) > -1) {
    return methods.indexOf(method) > -1
  }
  return methods.indexOf('other') > -1
}

function preFilter ($delegateRoot) {
  $root = $delegateRoot
  options = $root.find('.tab-primary').data('options')
  methods = []
  $root.find('input[data-toggle=method]:checked').each(function () {
    methods.push($(this).val())
  })
}

export function addMarkup ($node) {
  var $sidebar = $(sidebarHtml)
  var $expAll = $node.find('.tab-panes > .tab-primary > .tab-body > .expand-all')
  $node.find('.tab-panes > .tab-primary > .tab-body').before($sidebar)

  updateErrorSummary($node)
  phpErrorToggles($node)
  moveChannelToggles($node)
  addMethodToggles($node)
  if ($expAll.length) {
    $expAll.remove()
    $sidebar.find('.expand-all').show()
  }
  setTimeout(function () {
    $sidebar.removeClass('no-transition')
  }, 500)
}

export function close ($node) {
  $node.find('.debug-sidebar')
    .removeClass('show')
    .attr('style', '')
    .trigger('close.debug.sidebar')
  config.set('openSidebar', false)
}

export function open ($node) {
  $node.find('.debug-sidebar')
    .addClass('show')
    .trigger('open.debug.sidebar')
  config.set('openSidebar', true)
}

/**
 * @param $node debugroot
 */
function addMethodToggles ($node) {
  var channelNameRoot = $node.data('channelNameRoot')
  var $filters = $node.find('.debug-filters')
  var $entries = $node.find('.tab-primary').find('> .tab-panes .m_alert, .group-body > *')
  var val
  var haveEntry
  for (val in methodLabels) {
    haveEntry = val === 'other'
      ? $entries.not('.m_alert, .m_error, .m_warn, .m_info').length > 0
      : $entries.filter('.m_' + val).not('[data-channel="' + channelNameRoot + '.phpError"]').length > 0
    $filters.append(
      $('<li />').append(
        $('<label class="toggle active" />').toggleClass('disabled', !haveEntry).append(
          $('<input />', {
            type: 'checkbox',
            checked: true,
            'data-toggle': 'method',
            value: val
          })
        ).append(
          $('<span>').append(methodLabels[val])
        )
      )
    )
  }
}

/**
 * grab the .tab-panes toggles and move them to sidebar
 */
function moveChannelToggles ($node) {
  var $togglesSrc = $node.find('.tab-panes .channels > ul > li')
  var $togglesDest = $node.find('.debug-sidebar .channels ul')
  $togglesDest.append($togglesSrc)
  if ($togglesDest.children().length === 0) {
    $togglesDest.parent().hide()
  }
  $node.find('> .tab-panes > .tab-primary > .tab-body > .channels').remove()
}

/**
 * Build php error toggles
 */
function phpErrorToggles ($node) {
  var $togglesUl = $node.find('.debug-sidebar .php-errors ul')
  var categories = ['fatal', 'error', 'warning', 'deprecated', 'notice', 'strict']
  $.each(categories, function (i, category) {
    var count = category === 'fatal'
      ? $node.find('.m_alert.error-summary.have-fatal').length
      : $node.find('.error-' + category).filter('.m_error,.m_warn').length
    if (count === 0) {
      return
    }
    $togglesUl.append(
      $('<li>').append(
        $('<label class="toggle active">').html(
          '<input type="checkbox" checked data-toggle="error" data-count="' + count + '" value="' + category + '" />' +
          category + ' <span class="badge">' + count + '</span>'
        )
      )
    )
  })
  if ($togglesUl.children().length === 0) {
    $togglesUl.parent().hide()
  }
}

function updateErrorSummary ($node) {
  var $errorSummary = $node.closest('.debug').find('.m_alert.error-summary')
  var $inConsole = $errorSummary.find('.in-console')
  $inConsole.prev().remove()
  $inConsole.remove()
  if ($errorSummary.children().length === 0) {
    $errorSummary.remove()
  }
}

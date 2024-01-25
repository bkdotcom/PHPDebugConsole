/**
 * Filter entries
 */

import $ from 'jquery'

var channels = []
var tests = [
  function ($node) {
    var channel = $node.data('channel') || $node.closest('.debug').data('channelNameRoot')
    return channels.indexOf(channel) > -1
  }
]
var preFilterCallbacks = [
  function ($root) {
    var $checkboxes = $root.find('input[data-toggle=channel]')
    if ($checkboxes.length === 0) {
      channels = [$root.data('channelNameRoot')]
      return
    }
    channels = []
    $checkboxes.filter(':checked').each(function () {
      channels.push($(this).val())
    })
  }
]

export function init ($delegateNode) {
  /*
  var $debugTabLog = $delegateNode.find('> .tab-panes > .tab-primary')
  if ($debugTabLog.length > 0 && $debugTabLog.data('options').sidebar === false) {
    // no sidebar -> no filtering
    //    documentation uses non-sidebar filtering
    return
  }
  */
  applyFilter($delegateNode)
  $delegateNode.on('change', 'input[type=checkbox]', onCheckboxChange)
  $delegateNode.on('change', 'input[data-toggle=error]', onToggleErrorChange)
  $delegateNode.on('channelAdded.debug', function (e) {
    var $root = $(e.target).closest('.debug')
    updateFilterStatus($root)
  })
  $delegateNode.on('refresh.debug', function (e) {
    var $root = $(e.target).closest('.debug')
    applyFilter($root)
  })
  $delegateNode.on('shown.debug.tab', function (e) {
    hideSummarySeparator($(e.target))
  })
}

function hideSummarySeparator ($tabPane) {
  $tabPane.find('> .tab-body > hr').toggleClass(
    'filter-hidden',
    $tabPane.find('> .tab-body').find(' > .debug-log-summary, > .debug-log').filter(function () {
      return $(this).height() < 1
    }).length > 0
  )
}

function onCheckboxChange () {
  var $this = $(this)
  var isChecked = $this.is(':checked')
  var $nested = $this.closest('label').next('ul').find('input')
  var $root = $this.closest('.debug')
  if ($this.closest('.debug-options').length > 0) {
    // we're only interested in filter checkboxes
    return
  }
  if ($this.data('toggle') === 'error') {
    // filtered separately
    return
  }
  $nested.prop('checked', isChecked)
  applyFilter($root)
}

function onToggleErrorChange () {
  var $this = $(this)
  var isChecked = $this.is(':checked')
  var $root = $this.closest('.debug')
  var errorClass = $this.val()
  var selector = '.group-body .error-' + errorClass
  $root.find(selector).toggleClass('filter-hidden', !isChecked)
  // trigger collapse to potentially update group icon and add/remove empty class
  $root.find('.m_error, .m_warn').parents('.m_group')
    .trigger('collapsed.debug.group')
  updateFilterStatus($root)
}

export function addTest (func) {
  tests.push(func)
}

export function addPreFilter (func) {
  preFilterCallbacks.push(func)
}

function applyFilter ($root) {
  var channelNameRoot = $root.data('channelNameRoot')
  var i
  var len
  var sort = []
  for (i in preFilterCallbacks) {
    preFilterCallbacks[i]($root)
  }
  /*
    find all log entries and process them greatest depth to least depth
  */
  $root
    .find('> .tab-panes > .tab-primary > .tab-body')
    .find('.m_alert, .group-body > *:not(.m_groupSummary)')
    .each(function () {
      sort.push({
        depth: $(this).parentsUntil('.tab_body').length,
        node: $(this)
      })
    })
  sort.sort(function (a, b) {
    return a.depth < b.depth ? 1 : -1
  })
  for (i = 0, len = sort.length; i < len; i++) {
    var $node = sort[i].node
    applyFilterToNode($node, channelNameRoot)
  }
  hideSummarySeparator($root.find('> .tab-panes > .tab-pane.active'))
  updateFilterStatus($root)
}

function applyFilterToNode ($node, channelNameRoot) {
  var hiddenWas = $node.is('.filter-hidden')
  var isVis = true
  if ($node.data('channel') === channelNameRoot + '.phpError') {
    // php Errors are filtered separately
    return
  }
  isVis = isFilterVis($node)
  $node.toggleClass('filter-hidden', !isVis)
  if (isVis && hiddenWas) {
    // unhiding
    afterUnhide($node)
  } else if (!isVis && !hiddenWas) {
    // hiding
    afterHide($node)
  }
  if (isVis && $node.hasClass('m_group')) {
    // trigger to call groupUpdate
    $node.trigger('collapsed.debug.group')
  }
}

function afterUnhide ($node) {
  var $parentGroup = $node.parent().closest('.m_group')
  if (!$parentGroup.length || $parentGroup.hasClass('expanded')) {
    $node.debugEnhance()
  }
}

function afterHide ($node) {
  if ($node.hasClass('m_group')) {
    // filtering group... means children (if not filtered) are visible
    $node.find('> .group-body').debugEnhance()
  }
}

function isFilterVis ($node) {
  var i
  var isVis = true
  for (i in tests) {
    isVis = tests[i]($node)
    if (!isVis) {
      break
    }
  }
  return isVis
}

function updateFilterStatus ($root) {
  var haveUnchecked = $root.find('.debug-sidebar input:checkbox:not(:checked)').length > 0
  $root.toggleClass('filter-active', haveUnchecked)
}

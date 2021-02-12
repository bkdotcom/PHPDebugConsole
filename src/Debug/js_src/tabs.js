/**
 * handle tabs
 */

import $ from 'jquery'

export function init ($delegateNode) {
  // config = $delegateNode.data('config').get()
  var $debugTabs = $delegateNode.find('.tab-panes')
  $delegateNode.find('nav .nav-link').each(function () {
    var $tab = $(this)
    var targetSelector = $tab.data('target')
    var $tabPane = $debugTabs.find(targetSelector).eq(0)
    if ($tabPane.text().trim().length === 0) {
      $tab.hide()
    }
  })
  $delegateNode.on('click', '[data-toggle=tab]', function () {
    show(this)
    return false
  })
  $delegateNode.on('shown.debug.tab', function (e) {
    var $target = $(e.target)
    if ($target.hasClass('string-raw')) {
      $target.debugEnhance()
      return
    }
    $target.find('.m_alert, .group-body:visible').debugEnhance()
  })
}

function show (node) {
  var $tab = $(node)
  var targetSelector = $tab.data('target')
  // .tabs-container may wrap the nav and the tabs-panes...
  var $context = (function () {
    var $context = $tab.closest('.tabs-container')
    return $context.length
      ? $context
      : $tab.closest('.debug').find('.tab-panes')
  })()
  var $tabPane = $context.find(targetSelector).eq(0)
  $tab.siblings().removeClass('active')
  $tab.addClass('active')
  $tabPane.siblings().removeClass('active')
  $tabPane.addClass('active')
  $tabPane.trigger('shown.debug.tab')
}

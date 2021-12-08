/**
 * handle tabs
 */

import $ from 'jquery'

export function init ($delegateNode) {
  // config = $delegateNode.data('config').get()
  var $tabPanes = $delegateNode.find('.tab-panes')
  $delegateNode.find('nav .nav-link').each(function (i, tab) {
    initTab($(tab), $tabPanes)
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

function initTab ($tab, $tabPanes) {
  var targetSelector = $tab.data('target')
  var $tabPane = $tabPanes.find(targetSelector).eq(0)
  if ($tab.hasClass('active')) {
    // don't hide or highlight primary tab
    return // continue
  }
  if ($tabPane.text().trim().length === 0) {
    $tab.hide()
  } else if ($tabPane.find('.m_error').length) {
    $tab.addClass('has-error')
  } else if ($tabPane.find('.m_warn').length) {
    $tab.addClass('has-warn')
  } else if ($tabPane.find('.m_assert').length) {
    $tab.addClass('has-assert')
  }
}

function show (node) {
  var $tab = $(node)
  var targetSelector = $tab.data('target')
  // .tabs-container may wrap the nav and the tabs-panes...
  var $context = (function () {
    var $tabsContainer = $tab.closest('.tabs-container')
    return $tabsContainer.length
      ? $tabsContainer
      : $tab.closest('.debug').find('.tab-panes')
  })()
  var $tabPane = $context.find(targetSelector).eq(0)
  $tab.siblings().removeClass('active')
  $tab.addClass('active')
  $tabPane.siblings().removeClass('active')
  $tabPane.addClass('active')
  $tabPane.trigger('shown.debug.tab')
}

export function getNodeType ($node) {
  var matches = $node.prop('class').match(/t_(\w+)|(timestamp|string-encoded)/)
  var type
  var typeMore = $node.data('typeMore')
  if (matches === null) {
    return getNodeTypeNoMatch($node)
  }
  type = findFirstDefined(matches.slice(1)) || 'unknown'
  if (type === 'timestamp') {
    type = $node.find('> span').prop('class').replace('t_', '')
    typeMore = 'timestamp'
  } else if (type === 'string-encoded') {
    type = 'string'
    typeMore = $node.data('typeMore')
  }
  return [type, typeMore]
}

function findFirstDefined (list) {
  for (var i = 0, count = list.length; i < count; i++) {
    if (list[i] !== undefined) {
      return list[i]
    }
  }
}

function getNodeTypeNoMatch ($node) {
  var type = $node.data('type') || 'unknown'
  var typeMore = $node.data('typeMore')
  if ($node.hasClass('show-more-container')) {
    type = 'string'
  } else if ($node.hasClass('value-container') && $node.find('.content-type').length) {
    typeMore = $node.find('.content-type').text()
  }
  return [type, typeMore]
}

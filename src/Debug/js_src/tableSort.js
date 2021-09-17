import $ from 'jquery'

/**
 * Add sortability to given table
 */
export function makeSortable (table) {
  var $table = $(table)
  var $head = $table.find('> thead')
  if (!$table.is('table.sortable')) {
    return $table
  }
  $table.addClass('table-sort')
  $head.on('click', 'th', function () {
    var $th = $(this)
    var $cells = $(this).closest('tr').children()
    var i = $cells.index($th)
    var curDir = $th.is('.sort-asc') ? 'asc' : 'desc'
    var newDir = curDir === 'desc' ? 'asc' : 'desc'
    $cells.removeClass('sort-asc sort-desc')
    $th.addClass('sort-' + newDir)
    if (!$th.find('.sort-arrows').length) {
      // this th needs the arrow markup
      $cells.find('.sort-arrows').remove()
      $th.append('<span class="fa fa-stack sort-arrows float-right">' +
          '<i class="fa fa-caret-up" aria-hidden="true"></i>' +
          '<i class="fa fa-caret-down" aria-hidden="true"></i>' +
        '</span>')
    }
    sortTable($table[0], i, newDir)
  })
  return $table
}

/**
 * Sort table
 *
 * @param obj table dom element
 * @param int col   column index
 * @param str dir   (asc) or desc
 */
function sortTable (table, col, dir) {
  var body = table.tBodies[0]
  var rows = body.rows
  var i
  var floatRe = /^([+-]?(?:0|[1-9]\d*)(?:\.\d*)?)(?:[eE]([+-]?\d+))?$/
  var collator = typeof Intl.Collator === 'function'
    ? new Intl.Collator([], {
      numeric: true,
      sensitivity: 'base'
    })
    : false
  dir = dir === 'desc' ? -1 : 1
  rows = Array.prototype.slice.call(rows, 0) // Converts HTMLCollection to Array
  rows = rows.sort(function (trA, trB) {
    var a = trA.cells[col].textContent.trim()
    var b = trB.cells[col].textContent.trim()
    var afloat = a.match(floatRe)
    var bfloat = b.match(floatRe)
    var comp = 0
    if (afloat) {
      a = toFixed(a, afloat)
    }
    if (bfloat) {
      b = toFixed(b, bfloat)
    }
    if (afloat && bfloat) {
      if (a < b) {
        comp = -1
      } else if (a > b) {
        comp = 1
      }
      return dir * comp
    }
    comp = collator
      ? collator.compare(a, b)
      : a.localeCompare(b) // not a natural sort
    return dir * comp
  })
  for (i = 0; i < rows.length; ++i) {
    body.appendChild(rows[i]) // append each row in order (which moves)
  }
}

function toFixed(str, matches) {
  var num = Number.parseFloat(str)
  if (matches[2]) {
    // sci notation
    num = num.toFixed(6)
  }
  return num
}

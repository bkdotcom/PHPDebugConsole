export default (function(pdc){

	return {
		enhance: function($table) {
			if ($table.is("table.sortable")) {
				this.makeSortable($table);
			}
		},

		/**
		 * Add sortability to given table
		 */
		makeSortable: function(table) {
			var $ = pdc.jQuery,
				$table = $(table),
				$head = $table.find("> thead");
			$table.addClass("table-sort");
			$head.on("click", "th", function() {
				var $th = $(this),
					$cells = $(this).closest("tr").children(),
					i = $cells.index($th),
					curDir = $th.hasClass("sort-asc") ? "asc" : "desc",
					newDir = curDir === "desc" ? "asc" : "desc";
				$cells.removeClass("sort-asc sort-desc");
				$th.addClass("sort-"+newDir);
				if (!$th.find(".sort-arrows").length) {
					// this th needs the arrow markup
					$cells.find(".sort-arrows").remove();
					$th.append('<span class="fa fa-stack sort-arrows pull-right">' +
							'<i class="fa fa-caret-up" aria-hidden="true"></i>' +
							'<i class="fa fa-caret-down" aria-hidden="true"></i>' +
						"</span>");
				}
				sortTable($table[0], i, newDir);
			});
		}
	};

	/**
	 * sort table
	 *
	 * @param obj table dom element
	 * @param int col   column index
	 * @param str dir   (asc) or desc
	 */
	function sortTable(table, col, dir) {
		var body = table.tBodies[0],
			rows = body.rows,
			i,
			collator = typeof Intl.Collator === "function"
				? new Intl.Collator([], {
					numeric: true,
					sensitivity: 'base'
				})
				: false;
		dir = dir === "desc" ? -1 : 1;
		rows = Array.prototype.slice.call(rows, 0), // Converts HTMLCollection to Array
		rows = rows.sort(function (trA, trB) {
			var a = trA.cells[col].textContent.trim(),
				b = trB.cells[col].textContent.trim();
			return collator
				? dir * collator.compare(a, b)
				: dir * a.localeCompare(b);	// not a natural sort
		});
		for (i = 0; i < rows.length; ++i) {
			body.appendChild(rows[i]); // append each row in order (which moves)
		}
	}

}(PHPDebugConsole));

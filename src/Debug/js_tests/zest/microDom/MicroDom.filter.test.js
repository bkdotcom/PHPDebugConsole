import { default as zest } from '../../../js_src/zest/Zest.js';

describe('MicroDom filter methods', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="root">
        <div class="item first" id="item1">Item 1</div>
        <div class="item" id="item2">Item 2</div>
        <div class="item special" id="item3">Item 3</div>
        <div class="item" id="item4">Item 4</div>
        <div class="item last" id="item5">Item 5</div>
        <p class="paragraph" id="p1">Paragraph 1</p>
        <p class="paragraph" id="p2">Paragraph 2</p>
        <span class="span" id="span1">Span 1</span>
        <button id="btn" disabled>Button</button>
      </div>
    `;
  });

  describe('eq method', () => {
    test('returns element at specified index', () => {
      const $items = zest('.item');
      const $eq2 = $items.eq(2);

      expect($eq2.length).toBe(1);
      expect($eq2[0].id).toBe('item3');
    });

    test('handles negative indexes by counting from end', () => {
      const $items = zest('.item');
      const $eqNeg2 = $items.eq(-2);

      expect($eqNeg2.length).toBe(1);
      expect($eqNeg2[0].id).toBe('item4');
    });

    test('returns empty collection if index out of bounds', () => {
      const $items = zest('.item');
      const $tooLarge = $items.eq(10);
      const $tooSmall = $items.eq(-10);

      expect($tooLarge.length).toBe(0);
      expect($tooSmall.length).toBe(0);
    });

    test('works with empty collection', () => {
      const $empty = zest('.non-existent');
      expect($empty.eq(0).length).toBe(0);
    });
  });

  describe('filter method', () => {
    test('filters elements by selector', () => {
      const $items = zest('.item');
      expect($items.length).toBe(5);

      const $filtered = $items.filter('.special');
      expect($filtered.length).toBe(1);
      expect($filtered[0].id).toBe('item3');
    });

    test('filters elements by function', () => {
      const $items = zest('.item');
      const $filtered = $items.filter(function(el, i) {
        return i % 2 === 0; // Select even indices (0, 2, 4)
      });

      expect($filtered.length).toBe(3);
      expect($filtered[0].id).toBe('item1');
      expect($filtered[1].id).toBe('item3');
      expect($filtered[2].id).toBe('item5');
    });

    test('filters elements by MicroDom instance', () => {
      const $items = zest('.item');
      const $special = zest('#item3');

      const $filtered = $items.filter($special);
      expect($filtered.length).toBe(1);
      expect($filtered[0].id).toBe('item3');
    });

    test('filters elements by NodeList', () => {
      const $items = zest('.item');
      const nodeList = document.querySelectorAll('#item2, #item4');

      const $filtered = $items.filter(nodeList);
      expect($filtered.length).toBe(2);
      expect($filtered[0].id).toBe('item2');
      expect($filtered[1].id).toBe('item4');
    });

    test('filters elements by Node', () => {
      const $items = zest('.item');
      const node = document.getElementById('item5');

      const $filtered = $items.filter(node);
      expect($filtered.length).toBe(1);
      expect($filtered[0].id).toBe('item5');
    });

    test('returns empty collection when no matches', () => {
      const $items = zest('.item');
      const $filtered = $items.filter('.non-existent');

      expect($filtered.length).toBe(0);
    });
  });

  describe('first method', () => {
    test('returns first element in collection', () => {
      const $items = zest('.item');
      const $first = $items.first();

      expect($first.length).toBe(1);
      expect($first[0].id).toBe('item1');
    });

    test('returns empty collection if original is empty', () => {
      const $empty = zest('.non-existent');
      const $first = $empty.first();

      expect($first.length).toBe(0);
    });
  });

  describe('is method', () => {
    test('checks if any element matches selector', () => {
      const $items = zest('.item');

      expect($items.is('.special')).toBe(true);
      expect($items.is('.non-existent')).toBe(false);

      const $single = zest('#item3');
      expect($single.is('.special')).toBe(true);
      expect($single.is('.first')).toBe(false);
    });

    test('checks against function', () => {
      const $items = zest('.item');

      const result = $items.is(function(el) {
        return el.id === 'item3';
      });

      expect(result).toBe(true);

      const negResult = $items.is(function(el) {
        return el.id === 'non-existent';
      });

      expect(negResult).toBe(false);
    });

    test('checks against MicroDom instance', () => {
      const $items = zest('.item');
      const $special = zest('#item3');

      expect($items.is($special)).toBe(true);

      const $outside = zest('#p1');
      expect($items.is($outside)).toBe(false);
    });

    test('checks against NodeList', () => {
      const $items = zest('.item');
      const nodeList = document.querySelectorAll('#item2, #span1');

      expect($items.is(nodeList)).toBe(true);

      const outsideList = document.querySelectorAll('#p1, #p2');
      expect($items.is(outsideList)).toBe(false);
    });

    test('checks against Node', () => {
      const $items = zest('.item');
      const node = document.getElementById('item5');

      expect($items.is(node)).toBe(true);

      const outsideNode = document.getElementById('p1');
      expect($items.is(outsideNode)).toBe(false);
    });

    test('returns false for empty collection', () => {
      const $empty = zest('.non-existent');

      expect($empty.is('.anything')).toBe(false);
    });
  });

  describe('last method', () => {
    test('returns last element in collection', () => {
      const $items = zest('.item');
      const $last = $items.last();

      expect($last.length).toBe(1);
      expect($last[0].id).toBe('item5');
    });

    test('returns empty collection if original is empty', () => {
      const $empty = zest('.non-existent');
      const $last = $empty.last();

      expect($last.length).toBe(0);
    });
  });

  describe('not method', () => {
    test('removes elements matching selector', () => {
      const $items = zest('.item');
      expect($items.length).toBe(5);

      const $notSpecial = $items.not('.special');
      expect($notSpecial.length).toBe(4);

      // Verify item3 was removed
      const ids = Array.from($notSpecial).map(el => el.id);
      expect(ids).toContain('item1');
      expect(ids).toContain('item2');
      expect(ids).toContain('item4');
      expect(ids).toContain('item5');
      expect(ids).not.toContain('item3');
    });

    test('removes elements matching function', () => {
      const $items = zest('.item');
      const $notEvenIndex = $items.not(function(el, i) {
        return i % 2 === 0; // Remove elements at even indices
      });

      expect($notEvenIndex.length).toBe(2);
      expect($notEvenIndex[0].id).toBe('item2');
      expect($notEvenIndex[1].id).toBe('item4');
    });

    test('removes elements matching MicroDom instance', () => {
      const $items = zest('.item');
      const $toRemove = zest('#item1, #item5');

      const $filtered = $items.not($toRemove);
      expect($filtered.length).toBe(3);
      expect($filtered[0].id).toBe('item2');
      expect($filtered[1].id).toBe('item3');
      expect($filtered[2].id).toBe('item4');
    });

    test('removes elements matching NodeList', () => {
      const $items = zest('.item');
      const nodeList = document.querySelectorAll('#item2, #item4');

      const $filtered = $items.not(nodeList);
      expect($filtered.length).toBe(3);
      expect($filtered[0].id).toBe('item1');
      expect($filtered[1].id).toBe('item3');
      expect($filtered[2].id).toBe('item5');
    });

    test('removes elements matching Node', () => {
      const $items = zest('.item');
      const node = document.getElementById('item3');

      const $filtered = $items.not(node);
      expect($filtered.length).toBe(4);
      expect($filtered[0].id).toBe('item1');
      expect($filtered[1].id).toBe('item2');
      expect($filtered[2].id).toBe('item4');
      expect($filtered[3].id).toBe('item5');
    });

    test('returns original collection when no matches to remove', () => {
      const $items = zest('.item');
      const $filtered = $items.not('.non-existent');

      expect($filtered.length).toBe(5);
    });
  });
});

import { default as zest } from '../../../js_src/zest/Zest.js';

describe('MicroDom traversal methods', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="root">
        <div class="level-1" id="div1">
          <div class="level-2" id="div1-1">
            <span class="level-3 child" id="span1">First</span>
            <span class="level-3 child" id="span2">Second</span>
          </div>
        </div>
        <div class="level-1 sibling" id="div2">
          <p class="level-2" id="p1">Paragraph 1</p>
          <p class="level-2" id="p2">Paragraph 2</p>
          <p class="level-2" id="p3">Paragraph 3</p>
        </div>
        <div class="level-1 sibling" id="div3">
          <a class="level-2" id="link1" href="#">Link 1</a>
          <a class="level-2" id="link2" href="#">Link 2</a>
        </div>
        <div class="level-1 sibling" id="div4">Last Div</div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;
  });

  describe('children', () => {
    test('returns child elements', () => {
      const $div2 = zest('#div2');
      const $children = $div2.children();
      expect($children.length).toBe(3);
      expect($children[0].tagName).toBe('P');
      expect($children[0].id).toBe('p1');
    });

    test('filters children with selector', () => {
      const $div2 = zest('#div2');
      const $children = $div2.children('#p2');
      expect($children.length).toBe(1);
      expect($children[0].id).toBe('p2');
    });
  });

  describe('closest', () => {
    test('finds closest ancestor matching selector', () => {
      const $span = zest('#span1');
      const $closest = $span.closest('.level-1');
      expect($closest.length).toBe(1);
      expect($closest[0].id).toBe('div1');
    });

    test('returns self if matches selector', () => {
      const $div = zest('#div1');
      const $closest = $div.closest('.level-1');
      expect($closest.length).toBe(1);
      expect($closest[0]).toBe($div[0]);
    });

    test('returns empty collection when no match', () => {
      const $div = zest('#div1');
      const $closest = $div.closest('.non-existent');
      expect($closest.length).toBe(0);
    });
  });

  describe('find', () => {
    test('finds elements with selector', () => {
      const $root = zest('#root');
      const $spans = $root.find('span');
      expect($spans.length).toBe(2);
      expect($spans[0].id).toBe('span1');
      expect($spans[1].id).toBe('span2');
    });

    test('finds elements with MicroDom instance', () => {
      const $root = zest('#root');
      const $spans = zest('span');
      const $found = $root.find($spans);
      expect($found.length).toBe(2);
      expect($found[0].id).toBe('span1');
    });

    test('finds elements with NodeList', () => {
      const $root = zest('#root');
      const spans = document.querySelectorAll('span');
      const $found = $root.find(spans);
      expect($found.length).toBe(2);
      expect($found[0].id).toBe('span1');
    });

    test('finds elements with Node', () => {
      const $root = zest('#root');
      const span = document.getElementById('span1');
      const $found = $root.find(span);
      expect($found.length).toBe(1);
      expect($found[0].id).toBe('span1');
    });

    test('empty MicroDom instance', () => {
      const $root = zest('#root');
      const $empty = zest('.non-existent');
      const $found = $root.find($empty);
      expect($found.length).toBe(0);
    });

    test('element not contained in collection', () => {
      const $div1 = zest('#div1');
      const $div2 = zest('#div2');
      const $found = $div1.find($div2);
      expect($found.length).toBe(0);
    });
  });

  describe('next', () => {
    test('gets next element sibling', () => {
      const $div1 = zest('#div1');
      const $next = $div1.next();
      expect($next.length).toBe(1);
      expect($next[0].id).toBe('div2');
    });

    test('gets next element sibling with filter', () => {
      const $div2 = zest('#div2');
      const $next = $div2.next('.level-1');
      expect($next.length).toBe(1);
      expect($next[0].id).toBe('div3');
    });

    test('returns empty when no next sibling', () => {
      const $header = zest('#header');
      const $next = $header.next();
      expect($next.length).toBe(0);
    });
  });

  describe('nextAll', () => {
    test('gets all next element siblings', () => {
      const $div1 = zest('#div1');
      const $nextAll = $div1.nextAll();
      expect($nextAll.length).toBe(6);
      expect($nextAll[0].id).toBe('div2');
      expect($nextAll[1].id).toBe('div3');
    });

    test('gets all next element siblings with filter', () => {
      const $div1 = zest('#div1');
      const $nextAll = $div1.nextAll('.sibling');
      expect($nextAll.length).toBe(3);
      expect($nextAll[0].id).toBe('div2');
      expect($nextAll[1].id).toBe('div3');
      expect($nextAll[2].id).toBe('div4');
    });

    test('returns empty when no next siblings', () => {
      const $header = zest('#header');
      const $nextAll = $header.nextAll();
      expect($nextAll.length).toBe(0);
    });
  });

  describe('nextUntil', () => {
    test('gets all next siblings until selector match', () => {
      const $div1 = zest('#div1');
      const $nextUntil = $div1.nextUntil('#div4');
      expect($nextUntil.length).toBe(2);
      expect($nextUntil[0].id).toBe('div2');
      expect($nextUntil[1].id).toBe('div3');
    });

    test('gets all next siblings until selector match with filter', () => {
      const $div1 = zest('#div1');
      const $nextUntil = $div1.nextUntil('#header', '.sibling');
      expect($nextUntil.length).toBe(3);
      expect($nextUntil[0].id).toBe('div2');
      expect($nextUntil[1].id).toBe('div3');
      expect($nextUntil[2].id).toBe('div4');
    });

    test('gets all next siblings when selector never matches', () => {
      const $div1 = zest('#div1');
      const $nextUntil = $div1.nextUntil('.non-existent');
      expect($nextUntil.length).toBe(6);
    });
  });

  describe('parent', () => {
    test('gets parent node', () => {
      const $div1 = zest('#div1');
      const $parent = $div1.parent();
      expect($parent.length).toBe(1);
      expect($parent[0].id).toBe('root');
    });

    test('gets parent node with filter', () => {
      const $spans = zest('span');
      const $parent = $spans.parent('#div1-1');
      expect($parent.length).toBe(1);
      expect($parent[0].id).toBe('div1-1');
    });

    test('filters out non-matching parents', () => {
      const $elements = zest('#span1, #p1');
      const $parents = $elements.parent('#div1-1');
      expect($parents.length).toBe(1);
      expect($parents[0].id).toBe('div1-1');
    });
  });

  describe('parents', () => {
    test('gets all parent nodes', () => {
      const $span = zest('#span1');
      const $parents = $span.parents();
      expect($parents.length).toBeGreaterThan(3);
      expect($parents[0].id).toBe('div1-1');
      expect($parents[1].id).toBe('div1');
      expect($parents[2].id).toBe('root');
    });

    test('gets all parent nodes with filter', () => {
      const $span = zest('#span1');
      const $parents = $span.parents('.level-1');
      expect($parents.length).toBe(1);
      expect($parents[0].id).toBe('div1');
    });
  });

  describe('parentsUntil', () => {
    test('gets all parent nodes until selector match', () => {
      const $span = zest('#span1');
      const $parentsUntil = $span.parentsUntil('#root');
      expect($parentsUntil.length).toBe(2);
      expect($parentsUntil[0].id).toBe('div1-1');
      expect($parentsUntil[1].id).toBe('div1');
    });

    test('gets all parent nodes until selector match with filter', () => {
      const $span = zest('#span1');
      const $parentsUntil = $span.parentsUntil('body', '.level-1');
      expect($parentsUntil.length).toBe(1);
      expect($parentsUntil[0].id).toBe('div1');
    });

    test('stops at body if selector never matches', () => {
      const $span = zest('#span1');
      const $parentsUntil = $span.parentsUntil('.non-existent');
      expect($parentsUntil.length).toBeGreaterThan(3);
    });
  });

  describe('prev', () => {
    test('gets previous element sibling', () => {
      const $div2 = zest('#div2');
      const $prev = $div2.prev();
      expect($prev.length).toBe(1);
      expect($prev[0].id).toBe('div1');
    });

    test('gets previous element sibling with filter', () => {
      const $div3 = zest('#div3');
      const $prev = $div3.prev('.level-1');
      expect($prev.length).toBe(1);
      expect($prev[0].id).toBe('div2');
    });

    test('returns empty when no previous sibling', () => {
      const $div1 = zest('#div1');
      const $prev = $div1.prev();
      expect($prev.length).toBe(0);
    });
  });

  describe('prevAll', () => {
    test('gets all previous element siblings', () => {
      const $btn = zest('#btn');
      const $prevAll = $btn.prevAll();
      expect($prevAll.length).toBe(4);
      expect($prevAll[0].id).toBe('div4');
      expect($prevAll[3].id).toBe('div1');
    });

    test('gets all previous element siblings with filter', () => {
      const $header = zest('#header');
      const $prevAll = $header.prevAll('.level-1');
      expect($prevAll.length).toBe(4);
    });

    test('returns empty when no previous siblings', () => {
      const $div1 = zest('#div1');
      const $prevAll = $div1.prevAll();
      expect($prevAll.length).toBe(0);
    });
  });

  describe('prevUntil', () => {
    test('gets all previous siblings until selector match', () => {
      const $header = zest('#header');
      const $prevUntil = $header.prevUntil('#div2');
      expect($prevUntil.length).toBe(4);
      expect($prevUntil[0].id).toBe('chk');
      expect($prevUntil[1].id).toBe('btn');
      expect($prevUntil[2].id).toBe('div4');
      expect($prevUntil[3].id).toBe('div3');
    });

    test('gets all previous siblings with filter', () => {
      const $header = zest('#header');
      const $prevUntil = $header.prevUntil('#div1', '.level-1');
      expect($prevUntil.length).toBe(3);
    });

    test('gets all previous siblings when selector never matches', () => {
      const $btn = zest('#btn');
      const $prevUntil = $btn.prevUntil('.non-existent');
      expect($prevUntil.length).toBe(4);
    });
  });

  describe('siblings', () => {
    test('gets all sibling elements', () => {
      const $div2 = zest('#div2');
      const $siblings = $div2.siblings();
      expect($siblings.length).toBe(6);
    });

    test('gets all sibling elements with filter', () => {
      const $div2 = zest('#div2');
      const $siblings = $div2.siblings('.level-1');
      expect($siblings.length).toBe(3);
      expect($siblings[0].id).toBe('div1');
      expect($siblings[1].id).toBe('div3');
      expect($siblings[2].id).toBe('div4');
    });

    test('returns empty when no siblings', () => {
      const $only = zest('<div id="only-child"></div>');
      zest('#div4').append($only);
      expect($only.siblings().length).toBe(0);
      $only.remove();
    });
  });

  // Legacy tests
  /*
  xtest('find, filter, is, not, eq, first, last', () => {
    const $foo = zest('.level-1');
    expect($foo.find('span.child').length).toBe(2);
    expect($foo.filter('#div2').length).toBe(1);
    expect($foo.is('#div1')).toBe(true);
    expect($foo.not('#div1').length).toBe(3);
    expect($foo.eq(0)[0].id).toBe('div1');
    expect($foo.first()[0].id).toBe('div1');
    expect($foo.last()[0].id).toBe('div4');
  });

  xtest('parent, children, siblings, next, prev, closest', () => {
    const $span = zest('span.child');
    expect($span.parent()[0].id).toBe('div1-1');
    expect(zest('#div1-1').children().length).toBe(2);
    expect($span.first().siblings().length).toBe(1);
    expect(zest('#div1').next()[0].id).toBe('div2');
    expect(zest('#div2').prev()[0].id).toBe('div1');
    expect($span.closest('.level-1')[0].id).toBe('div1');
  });
  */
});

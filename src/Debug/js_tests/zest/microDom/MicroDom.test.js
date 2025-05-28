import { default as zest } from '../../../js_src/zest/Zest.js';

describe('MicroDom core methods', () => {
  let container;

  let spyScrollTo = jest.fn();
  Object.defineProperty(global.window, 'scrollTo', {
    value: spyScrollTo ,
  });

  beforeEach(() => {
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;

    container = document.getElementById('root');
  });

  test('constructor properly filters and sets elements', () => {
    // Test with valid elements
    const $div = zest('#div1, #div2');
    expect($div.length).toBe(2);
    expect($div[0].id).toBe('div1');
    expect($div[1].id).toBe('div2');

    // Test with null/undefined elements
    const elements = [document.getElementById('div1'), null, undefined, document.getElementById('div2')];
    const customMicroDom = new zest.fn.constructor(...elements);
    expect(customMicroDom.length).toBe(2);
  });

  test('add method', () => {
    const $div1 = zest('#div1');
    // Add element by selector
    const $combined = $div1.add('#div2');
    expect($combined.length).toBe(2);
    expect($combined[1].id).toBe('div2');

    // Add element node
    const $addNode = $div1.add(document.getElementById('btn'));
    expect($addNode.length).toBe(2);
    expect($addNode[1].id).toBe('btn');

    // Add MicroDom instance
    const $btn = zest('#btn');
    const $addInstance = $div1.add($btn);
    expect($addInstance.length).toBe(2);

    // Add HTML string
    const $addHtml = $div1.add('<p id="new-p">New</p>');
    expect($addHtml.length).toBe(2);
    expect($addHtml[1].tagName).toBe('P');

    // Add array
    const $addArray = $div1.add([document.getElementById('btn'), document.getElementById('chk')]);
    expect($addArray.length).toBe(3);

    // Should remove duplicates
    const $noDupes = $div1.add('#div1');
    expect($noDupes.length).toBe(1);
  });

  test('alter method', () => {
    const $divs = zest('.foo');
    const $altered = $divs.alter(el => el.querySelector('span'));
    expect($altered.length).toBe(1);
    expect($altered[0].className).toBe('child');

    // With null return value
    const $alteredNull = $divs.alter(() => null);
    expect($alteredNull.length).toBe(0);

    // With single node return value
    const $alteredSingle = $divs.alter(() => document.getElementById('btn'));
    expect($alteredSingle.length).toBe(1);
    expect($alteredSingle[0].id).toBe('btn');

    // With filter
    const $alteredFilter = $divs.alter(
      () => document.querySelectorAll('#div1, #div2, #btn'),
      '#div2'
    );
    expect($alteredFilter.length).toBe(1);
    expect($alteredFilter[0].id).toBe('div2');
  });

  test('clone method', () => {
    const $div = zest('#div1');
    const $clone = $div.clone();
    expect($clone.length).toBe(1);
    expect($clone[0].id).toBe('div1');
    expect($clone[0]).not.toBe($div[0]);
  });

  test('each method', () => {
    const $divs = zest('.foo');
    const ids = [];
    $divs.each(function (el, i) {
      ids.push(el.id);
      expect(i).toBe(ids.length - 1);
      expect(el).toBe($divs[i]);
      expect(this).toBe(el); // 'this' should be bound to element
    });
    expect(ids).toEqual(['div1', 'div2']);
  });

  test('after method', () => {
    const $div = zest('#div1');

    // String
    $div.after('<p id="after1">After1</p>');
    expect($div[0].nextElementSibling.id).toBe('after1');

    // Node
    const p = document.createElement('p');
    p.id = 'after2';
    $div.after(p);
    expect($div[0].nextElementSibling.id).toBe('after2');

    // MicroDom
    const $p = zest('<p id="after3">After3</p>');
    $div.after($p);
    expect($div[0].nextElementSibling.id).toBe('after3');

    // Function
    $div.after((el, i) => `<p id="after-fn-${i}">After Fn</p>`);
    expect($div[0].nextElementSibling.id).toBe('after-fn-0');

    // Array
    const arr = ['<p id="after-arr1">After Arr1</p>', '<p id="after-arr2">After Arr2</p>'];
    $div.after(arr);
    const nextIds = Array.from($div[0].parentNode.children)
      .map(el => el.id)
      .filter(id => id.startsWith('after-arr'));
    expect(nextIds).toEqual(['after-arr1', 'after-arr2']);
  });

  test('append method', () => {
    const $div = zest('#div1');
    const initialChildCount = $div[0].childNodes.length;

    // String
    $div.append('<p class="appended">Appended</p>');
    expect($div[0].querySelector('.appended')).not.toBeNull();

    // Node
    const p = document.createElement('p');
    p.className = 'appended-node';
    $div.append(p);
    expect($div[0].querySelector('.appended-node')).not.toBeNull();

    // MicroDom
    const $p = zest('<p class="appended-microdom">Appended MicroDom</p>');
    $div.append($p);
    expect($div[0].querySelector('.appended-microdom')).not.toBeNull();

    // Function
    $div.append((el, i) => `<p class="appended-fn-${i}">Appended Fn</p>`);
    expect($div[0].querySelector('.appended-fn-0')).not.toBeNull();

    // Array
    const arr = ['<p class="appended-arr1">Appended Arr1</p>', '<p class="appended-arr2">Appended Arr2</p>'];
    $div.append(arr);
    expect($div[0].querySelector('.appended-arr1')).not.toBeNull();
    expect($div[0].querySelector('.appended-arr2')).not.toBeNull();

    // Verify all appends worked
    expect($div[0].childNodes.length).toBeGreaterThan(initialChildCount);
  });

  test('before method', () => {
    const $div = zest('#div2');

    // String
    $div.before('<p id="before1">Before1</p>');
    expect($div[0].previousElementSibling.id).toBe('before1');

    // Node
    const p = document.createElement('p');
    p.id = 'before2';
    $div.before(p);
    expect($div[0].previousElementSibling.id).toBe('before2');

    // MicroDom
    const $p = zest('<p id="before3">Before3</p>');
    $div.before($p);
    expect($div[0].previousElementSibling.id).toBe('before3');

    // Function
    $div.before((el, i) => `<p id="before-fn-${i}">Before Fn</p>`);
    expect($div[0].previousElementSibling.id).toBe('before-fn-0');

    // Array
    const arr = ['<p id="before-arr1">Before Arr1</p>', '<p id="before-arr2">Before Arr2</p>'];
    $div.before(arr);
    const prevIds = Array.from($div[0].parentNode.children)
      .map(el => el.id)
      .filter(id => id.startsWith('before-arr'));
    expect(prevIds).toEqual(['before-arr1', 'before-arr2']);
  });

  test('empty method', () => {
    const $div = zest('#div1');
    expect($div[0].childNodes.length).toBeGreaterThan(0);
    $div.empty();
    expect($div[0].childNodes.length).toBe(0);
  });

  test('html method', () => {
    const $div = zest('#div1');

    // Getter
    expect($div.html()).toMatch(/Hello <span class="child">World<\/span>/);

    // Setter - string
    $div.html('<b>Bold</b>');
    expect($div[0].innerHTML).toBe('<b>Bold</b>');

    // Setter - function
    $div.html((el, i, oldHtml) => {
      expect(oldHtml).toBe('<b>Bold</b>');
      return '<i>Italic</i>';
    });
    expect($div[0].innerHTML).toBe('<i>Italic</i>');

    // Setter - MicroDom
    $div.html(zest('<p>MicroDom</p>'));
    expect($div[0].innerHTML).toBe('<p>MicroDom</p>');

    // Setter - Node
    const node = document.createElement('span');
    node.textContent = 'Node';
    $div.html(node);
    expect($div[0].innerHTML).toBe('<span>Node</span>');

    // Empty collection
    const $empty = zest('.non-existent');
    expect($empty.html()).toBeUndefined();
  });

  test('index method', () => {
    const $allDivs = zest('#root > *');
    const $div1 = zest('#div1');
    const $div2 = zest('#div2');

    // No args - get position relative to siblings
    expect($div1.index()).toBe(0);
    expect($div2.index()).toBe(1);

    // With selector
    expect($allDivs.index('#btn')).toBe(2);

    // With MicroDom instance
    expect($allDivs.index($div2)).toBe(1);

    // With NodeList
    const nodeList = document.querySelectorAll('#div2');
    expect($allDivs.index(nodeList)).toBe(1);

    // Empty collection
    const $empty = zest('.non-existent');
    expect($empty.index()).toBe(-1);

    // Not found
    expect($allDivs.index('#non-existent')).toBe(-1);
    expect($allDivs.index(zest('#non-existent'))).toBe(-1);
  });

  test('prepend method', () => {
    const $div = zest('#div1');

    // String
    $div.prepend('<p class="prepended">Prepended</p>');
    expect($div[0].firstChild.className).toBe('prepended');

    // Node
    const p = document.createElement('p');
    p.className = 'prepended-node';
    $div.prepend(p);
    expect($div[0].firstChild.className).toBe('prepended-node');

    // MicroDom
    const $p = zest('<p class="prepended-microdom">Prepended MicroDom</p>');
    $div.prepend($p);
    expect($div[0].firstChild.className).toBe('prepended-microdom');

    // Function
    $div.prepend((el, i) => `<p class="prepended-fn-${i}">Prepended Fn</p>`);
    expect($div[0].firstChild.className).toBe(`prepended-fn-0`);

    // Array
    const arr = ['<p class="prepended-arr1">Prepended Arr1</p>', '<p class="prepended-arr2">Prepended Arr2</p>'];
    $div.prepend(arr);
    expect($div[0].firstChild.className).toBe('prepended-arr1');
    expect($div[0].firstChild.nextSibling.className).toBe('prepended-arr2');
  });

  test('remove method', () => {
    // Remove without selector
    const $btn = zest('#btn');
    expect(document.getElementById('btn')).not.toBeNull();
    let $new = $btn.remove();
    expect($new.length).toBe(0);
    expect(document.getElementById('btn')).toBeNull();

    // Remove with selector
    const $divs = zest('.foo');
    expect($divs.length).toBe(2);
    $new = $divs.remove('#div1');
    expect($new.length).toBe(1);
    expect(document.getElementById('div1')).toBeNull();
    expect(document.getElementById('div2')).not.toBeNull();
  });

  test('replaceWith method', () => {
    const $div1 = zest('#div1');

    // String
    $div1.replaceWith('<p id="replaced1">Replaced1</p>');
    expect(document.getElementById('div1')).toBeNull();
    expect(document.getElementById('replaced1')).not.toBeNull();

    // Reset DOM
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;

    // Node
    const $div = zest('#div1');
    const p = document.createElement('p');
    p.id = 'replaced2';
    $div.replaceWith(p);
    expect(document.getElementById('div1')).toBeNull();
    expect(document.getElementById('replaced2')).not.toBeNull();

    // Reset DOM
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;

    // MicroDom
    const $div1Again = zest('#div1');
    const $p = zest('<p id="replaced3">Replaced3</p>');
    $div1Again.replaceWith($p);
    expect(document.getElementById('div1')).toBeNull();
    expect(document.getElementById('replaced3')).not.toBeNull();

    // Reset DOM
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;

    // Function
    const $div1Once = zest('#div1');
    $div1Once.replaceWith((el, i) => `<p id="replaced-fn-${i}">Replaced Fn</p>`);
    expect(document.getElementById('div1')).toBeNull();
    expect(document.getElementById('replaced-fn-0')).not.toBeNull();

    // Reset DOM
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;

    // Array
    const $div1Final = zest('#div1');
    const arr = ['<p id="replaced-arr1">Replaced Arr1</p>', '<p id="replaced-arr2">Replaced Arr2</p>'];
    $div1Final.replaceWith(arr);
    expect(document.getElementById('div1')).toBeNull();
    expect(document.getElementById('replaced-arr1')).not.toBeNull();
    expect(document.getElementById('replaced-arr2')).not.toBeNull();
  });

  test('scrollTop method', () => {
    const $div = zest('#div1');
    let scrollTopValue = 0

    // Mock Element.scrollTop
    Object.defineProperty($div[0], 'scrollTop', {
      get: () => scrollTopValue,
      set: function (value) {
        scrollTopValue = value;
      },
    });

    // Getter
    $div.scrollTop();
    expect($div[0].scrollTop).toBe(0);

    // Setter
    $div.scrollTop(100);
    expect($div[0].scrollTop).toBe(100);

    // Mock window scrollTo
    const originalWindow = global.window;
    global.window = {
      ...originalWindow,
      scrollTo: jest.fn(),
      pageYOffset: 0
    };

    // Window object
    const $win = zest(global.window);
    $win.scrollTop(200);
    expect(global.window.scrollTo).toHaveBeenCalledWith(0, 200);

    // Reset window
    global.window = originalWindow;
  });

  test('text method', () => {
    const $div = zest('#div1');

    // Getter
    expect($div.text()).toMatch(/Hello World/);

    // Setter
    $div.text('New Text');
    expect($div[0].textContent).toBe('New Text');

    // Empty collection
    const $empty = zest('.non-existent');
    expect($empty.text()).toBe('');
  });

  test('wrap method', () => {
    const $div1 = zest('#div1');

    // String wrapper
    $div1.wrap('<div class="wrapper"><div class="inner-wrapper"></div></div>');
    expect($div1[0].parentNode.className).toBe('inner-wrapper');
    expect($div1[0].parentNode.parentNode.className).toBe('wrapper');

    // Reset DOM
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;

    // Element wrapper
    const $div = zest('#div1');
    const wrapper = document.createElement('div');
    wrapper.className = 'elem-wrapper';
    $div.wrap(wrapper);
    expect($div[0].parentNode.className).toBe('elem-wrapper');

    // Reset DOM
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;

    // Function wrapper
    const $div1Again = zest('#div1');
    $div1Again.wrap((el, i) => `<div class="fn-wrapper-${i}"></div>`);
    expect($div1Again[0].parentNode.className).toBe('fn-wrapper-0');
  });

  test('wrapInner method', () => {
    const $div1 = zest('#div1');
    const initialHtml = $div1.html();

    // String wrapper
    $div1.wrapInner('<div class="inner-wrapper"></div>');
    expect($div1[0].firstChild.className).toBe('inner-wrapper');
    expect($div1[0].firstChild.innerHTML).toBe(initialHtml);

    // Reset DOM
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;

    // Element wrapper
    const $div = zest('#div1');
    const initialHtml2 = $div.html();
    const wrapper = document.createElement('div');
    wrapper.className = 'elem-inner-wrapper';
    $div.wrapInner(wrapper);
    expect($div[0].firstChild.className).toBe('elem-inner-wrapper');
    expect($div[0].firstChild.innerHTML).toBe(initialHtml2);

    // Reset DOM
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;

    // Function wrapper
    const $div1Again = zest('#div1');
    const initialHtml3 = $div1Again.html();
    $div1Again.wrapInner((el, i) => `<div class="fn-inner-wrapper-${i}"></div>`);
    expect($div1Again[0].firstChild.className).toBe('fn-inner-wrapper-0');
    expect($div1Again[0].firstChild.innerHTML).toBe(initialHtml3);
  });

  test('html', () => {
    const $div = zest('#div1');
    expect($div.html()).toMatch('Hello <span class="child">World</span>');
    $div.html('<b>Bold</b>');
    expect($div[0].innerHTML).toBe('<b>Bold</b>');
  })

  test('text', () => {
    const $div = zest('#div1');
    $div.text('Plain');
    expect($div[0].textContent).toBe('Plain');
  });
});

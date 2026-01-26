import { default as zest } from '../../js_src/zest/Zest.js';
import * as helper from '../../js_src/zest/helper.js';

describe('Zest function', () => {
  let container;

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

  test('function runs on DOMContentLoaded', () => {
    const fn = jest.fn();
    zest(fn);
    document.dispatchEvent(new window.Event('DOMContentLoaded'));
    expect(fn).toHaveBeenCalled();
  });

  test('adds DOMContentLoaded listener if DOM is loading', () => {
    const fn = jest.fn();
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });
    zest(fn);
    document.dispatchEvent(new window.Event('DOMContentLoaded'));
    expect(fn).toHaveBeenCalled();
  });

  test('function called immediately if DOM is ready', () => {
    const fn = jest.fn();
    Object.defineProperty(document, 'readyState', { value: 'complete', configurable: true });
    zest(fn);
    expect(fn).toHaveBeenCalled();
  });

  test('selector returns MicroDom instance', () => {
    const $foo = zest('.foo');
    expect($foo).toBeInstanceOf(Array);
    expect($foo.constructor.name).toBe('MicroDom');
    expect($foo.length).toBe(2);
    expect($foo[0].id).toBe('div1');
    expect($foo[1].id).toBe('div2');
  });

  test('selector returns MicroDom', () => {
    document.body.innerHTML = '<div class="findme"></div>';
    const dom = zest('.findme');
    expect(dom[0].classList.contains('findme')).toBe(true);
  });

  test('MicroDom returns MicroDom', () => {
    const $dom = zest(document.body);
    expect($dom).toBeInstanceOf(Array);
    expect($dom.constructor.name).toBe('MicroDom');
    expect(zest($dom)).toBe($dom);
  });

  test('wraps Node or Window in MicroDom', () => {
    const dom = zest(document.body);
    expect(dom[0]).toBe(document.body);
    const win = zest(window);
    expect(win[0]).toBe(window);
  });

  test('undefined returns empty MicroDom', () => {
    const dom = zest();
    expect(dom.length).toBe(0);
  });

  test('html string returns MicroDom', () => {
    const $el = zest('<div class="new">New</div>');
    expect($el.length).toBe(1);
    expect($el[0].classList.contains('new')).toBe(true);
    expect($el[0].textContent).toBe('New');
  });

  test('creates elements from HTML string with attributes', () => {
    const dom = zest('<div class="bar"></div>', { id: 'baz' });
    expect(dom[0].id).toBe('baz');
    expect(dom[0].getAttribute('class')).toBe('bar');
  });

  test('creates complex HTML structures', () => {
    const complex = zest('<div><p>Text</p><span>More</span></div>');
    expect(complex.length).toBe(1);
    expect(complex[0].childNodes.length).toBe(2);
    expect(complex[0].firstChild.tagName).toBe('P');
    expect(complex[0].lastChild.tagName).toBe('SPAN');
  });
});

describe('helper.camelCase function', () => {
  test('converts kebab-case to camelCase', () => {
    expect(zest.camelCase('hello-world')).toBe('helloWorld');
  });

  test('converts snake_case to camelCase', () => {
    expect(zest.camelCase('hello_world')).toBe('helloWorld');
  });

  test('converts spaced string to camelCase', () => {
    expect(zest.camelCase('hello world')).toBe('helloWorld');
  });

  test('handles multiple separators', () => {
    expect(zest.camelCase('hello-world_example string')).toBe('helloWorldExampleString');
  });

  test('preserves already camelCase strings', () => {
    expect(zest.camelCase('helloWorld')).toBe('helloWorld');
  });

  test('preserves case of first word', () => {
    expect(zest.camelCase('Hello-world')).toBe('helloWorld');
  });
});

describe('helper.each function', () => {
  test('iterates over arrays', () => {
    const arr = [1, 2, 3];
    const result = [];
    const contexts = [];

    zest.each(arr, function(value, index) {
      result.push(value * 2);
      contexts.push(this);
    });

    expect(result).toEqual([2, 4, 6]);
    expect(contexts).toEqual([1, 2, 3]); // 'this' should be the value
  });

  test('iterates over objects', () => {
    const obj = { a: 1, b: 2, c: 3 };
    const keys = [];
    const values = [];
    const contexts = [];

    zest.each(obj, function(value, key) {
      keys.push(key);
      values.push(value);
      contexts.push(this);
    });

    expect(keys).toEqual(['a', 'b', 'c']);
    expect(values).toEqual([1, 2, 3]);
    expect(contexts).toEqual([1, 2, 3]); // 'this' should be the value
  });

  test('handles empty arrays', () => {
    const arr = [];
    const result = [];

    zest.each(arr, function(value) {
      result.push(value);
    });

    expect(result).toEqual([]);
  });

  test('handles empty objects', () => {
    const obj = {};
    const result = [];

    zest.each(obj, function(value) {
      result.push(value);
    });

    expect(result).toEqual([]);
  });
});

describe('helper.createElements function', () => {
  test('creates a single element', () => {
    const elements = helper.createElements('<div></div>');
    expect(elements.length).toBe(1);
    expect(elements[0].tagName).toBe('DIV');
  });

  test('creates multiple elements', () => {
    const elements = helper.createElements('<div></div><span></span>');
    expect(elements.length).toBe(2);
    expect(elements[0].tagName).toBe('DIV');
    expect(elements[1].tagName).toBe('SPAN');
  });

  test('creates elements with attributes', () => {
    const elements = helper.createElements('<div id="test" class="foo"></div>');
    expect(elements[0].id).toBe('test');
    expect(elements[0].className).toBe('foo');
  });

  test('creates elements with nested structure', () => {
    const elements = helper.createElements('<div><span>Text</span></div>');
    expect(elements[0].firstChild.tagName).toBe('SPAN');
    expect(elements[0].firstChild.textContent).toBe('Text');
  });

  test('handles text nodes', () => {
    const elements = helper.createElements('Text node');
    expect(elements[0].nodeType).toBe(Node.TEXT_NODE);
    expect(elements[0].textContent).toBe('Text node');
  });
});

describe('helper.extend function', () => {
  test('shallow extends an object', () => {
    const target = { a: 1 };
    const source = { b: 2 };

    const result = zest.extend(target, source);

    expect(result).toBe(target); // Returns the target
    expect(target).toEqual({ a: 1, b: 2 });
  });

  test('overwrites existing properties', () => {
    const target = { a: 1, b: 2 };
    const source = { b: 3 };

    zest.extend(target, source);

    expect(target).toEqual({ a: 1, b: 3 });
  });

  test('extends from multiple sources', () => {
    const target = { a: 1 };
    const source1 = { b: 2 };
    const source2 = { c: 3 };

    zest.extend(target, source1, source2);

    expect(target).toEqual({ a: 1, b: 2, c: 3 });
  });

  test('performs deep extension with true flag', () => {
    const target = { a: 1, obj: { x: 10 } };
    const source = { b: 2, obj: { y: 20 } };

    zest.extend(true, target, source);

    expect(target).toEqual({ a: 1, b: 2, obj: { x: 10, y: 20 } });
  });

  test('deep extension overwrites non-objects', () => {
    const target = { a: { x: 1 } };
    const source = { a: 2 };

    zest.extend(true, target, source);

    expect(target).toEqual({ a: 2 });
  });

  test('deep extension handles null values correctly', () => {
    const target = { a: null };
    const source = { a: { b: 2 } };

    zest.extend(true, target, source);

    expect(target).toEqual({ a: { b: 2 } });
  });

  test('merges arrays with unique values only', () => {
    const target = { arr: [1, 2, 3] };
    const source = { arr: [3, 4, 5] };

    zest.extend(true, target, source);

    expect(target.arr).toEqual([1, 2, 3, 4, 5]);
    expect(target.arr.length).toBe(5);
    // Verify no duplicates
    const uniqueValues = [...new Set(target.arr)];
    expect(target.arr.length).toBe(uniqueValues.length);
  });

  test('merges multiple arrays keeping only unique values', () => {
    const target = { arr: ['a', 'b', 'c'] };
    const source1 = { arr: ['b', 'c', 'd'] };
    const source2 = { arr: ['c', 'd', 'e'] };

    zest.extend(true, target, source1, source2);

    expect(target.arr).toEqual(['a', 'b', 'c', 'd', 'e']);
    expect(target.arr.length).toBe(5);
    // Verify no duplicates
    const uniqueValues = [...new Set(target.arr)];
    expect(target.arr.length).toBe(uniqueValues.length);
  });

  test('top-level array merge with duplicates removed', () => {
    const target = [1, 2, 3];
    const source = [3, 4, 5];

    const result = zest.extend(target, source);

    expect(result).toBe(target);
    expect(target).toEqual([1, 2, 3, 4, 5]);
    expect(target.length).toBe(5);
    // Verify no duplicates
    const uniqueValues = [...new Set(target)];
    expect(target.length).toBe(uniqueValues.length);
  });
});

describe('helper.isNumeric function', () => {
  test('identifies integers as numeric', () => {
    expect(zest.isNumeric(123)).toBe(true);
    expect(zest.isNumeric(-123)).toBe(true);
  });

  test('identifies floats as numeric', () => {
    expect(zest.isNumeric(123.45)).toBe(true);
    expect(zest.isNumeric(-123.45)).toBe(true);
  });

  test('identifies numeric strings as numeric', () => {
    expect(zest.isNumeric("123")).toBe(true);
    expect(zest.isNumeric("-123.45")).toBe(true);
  });

  test('identifies non-numeric values correctly', () => {
    expect(zest.isNumeric({})).toBe(false);
    expect(zest.isNumeric([])).toBe(false);
    expect(zest.isNumeric(null)).toBe(false);
    expect(zest.isNumeric(undefined)).toBe(false);
    expect(zest.isNumeric("abc")).toBe(false);
    expect(zest.isNumeric("123abc")).toBe(false);
    expect(zest.isNumeric(NaN)).toBe(false);
  });

  test('handles hex and other formats properly', () => {
    expect(zest.isNumeric("0x10")).toBe(true); // Hex string is numeric
  });
});

describe('helper.type function', () => {
  test('identifies primitive types', () => {
    expect(zest.type(123)).toBe('number');
    expect(zest.type("string")).toBe('string');
    expect(zest.type(true)).toBe('boolean');
    expect(zest.type(Symbol())).toBe('symbol');
  });

  test('identifies null and undefined', () => {
    expect(zest.type(null)).toBe('null');
    expect(zest.type(undefined)).toBe('undefined');
  });

  test('identifies core JavaScript objects', () => {
    expect(zest.type([])).toBe('array');
    expect(zest.type({})).toBe('object');
    expect(zest.type(new Date())).toBe('date');
    expect(zest.type(/regex/)).toBe('regexp');
    expect(zest.type(new Error())).toBe('error');
  });

  test('identifies custom objects as object', () => {
    class TestClass {
      // empty
    }
    expect(zest.type(new TestClass())).toBe('object');
  });

  test('identifies functions', () => {
    expect(zest.type(function() {})).toBe('function');
    expect(zest.type(() => {})).toBe('function');
  });
});

describe('helper.elInitMicroDomInfo function', () => {
  test('initializes microdom info on element', () => {
    const el = document.createElement('div');

    helper.elInitMicroDomInfo(el);

    expect(el[helper.rand]).toBeDefined();
    expect(el[helper.rand].data).toEqual({});
    expect(el[helper.rand].eventHandlers).toEqual([]);
  });

  test('stores display value for non-hidden elements', () => {
    const el = document.createElement('div');
    document.body.appendChild(el);

    // Mock getComputedStyle to return a known display value
    const originalGetComputedStyle = window.getComputedStyle;
    window.getComputedStyle = jest.fn().mockReturnValue({ display: 'block' });

    helper.elInitMicroDomInfo(el);

    expect(el[helper.rand].display).toBe('block');

    // Restore the original function
    window.getComputedStyle = originalGetComputedStyle;
  });

  test('does not overwrite existing microdom info', () => {
    const el = document.createElement('div');
    el[helper.rand] = {
      data: { existing: 'data' },
      eventHandlers: [{ test: 'handler' }]
    };

    helper.elInitMicroDomInfo(el);

    expect(el[helper.rand].data).toEqual({ existing: 'data' });
    expect(el[helper.rand].eventHandlers).toEqual([{ test: 'handler' }]);
  });

  test('skips display setup for window object', () => {
    helper.elInitMicroDomInfo(window);

    expect(window[helper.rand].display).toBeUndefined();
  });
});

describe('helper.argsToElements function', () => {
  test('does not convert selectors to elements (default)', () => {
    document.body.innerHTML = '<div id="test"></div>';
    const elements = helper.argsToElements(['.non-existent', '#test']);

    expect(elements.length).toBe(2);
    expect(elements[0].nodeType).toBe(Node.TEXT_NODE);
    expect(elements[0].textContent).toBe('.non-existent');
    expect(elements[1].nodeType).toBe(Node.TEXT_NODE);
    expect(elements[1].textContent).toBe('#test');
  });

  test('converts css selectors to elements', () => {
    document.body.innerHTML = '<div id="test"></div>';
    const elements = helper.argsToElements(['.non-existent', '#test'], true);

    expect(elements.length).toBe(1);
    expect(elements[0].id).toBe('test');
  });

  test('converts HTML strings to elements', () => {
    const elements = helper.argsToElements(['<div></div>']);

    expect(elements.length).toBe(1);
    expect(elements[0].tagName).toBe('DIV');
  });

  test('includes Node instances', () => {
    const node = document.createElement('div');
    const elements = helper.argsToElements([node]);

    expect(elements.length).toBe(1);
    expect(elements[0]).toBe(node);
  });

  test('converts NodeList to elements array', () => {
    document.body.innerHTML = '<div class="test"></div><div class="test"></div>';
    const nodeList = document.querySelectorAll('.test');
    const elements = helper.argsToElements([nodeList]);

    expect(elements.length).toBe(2);
    expect(elements[0]).toBe(nodeList[0]);
    expect(elements[1]).toBe(nodeList[1]);
  });

  test('flattens iterables', () => {
    const div1 = document.createElement('div');
    const div2 = document.createElement('div');
    const elements = helper.argsToElements([[div1, div2]]);

    expect(elements.length).toBe(2);
    expect(elements[0]).toBe(div1);
    expect(elements[1]).toBe(div2);
  });

  test('executes functions and uses return value', () => {
    const div = document.createElement('div');
    const fn = jest.fn(() => div);
    const elements = helper.argsToElements([fn]);

    expect(elements.length).toBe(1);
    expect(elements[0]).toBe(div);
    expect(fn).toHaveBeenCalled();
  });

  test('handles mixed argument types', () => {
    const div1 = document.createElement('div');
    document.body.innerHTML = '<div id="test3"></div>';

    const elements = helper.argsToElements([
      div1,
      '<div id="test2"></div>',
      '#test3'
    ], true);

    expect(elements.length).toBe(3);
    expect(elements[0]).toBe(div1);
    expect(elements[1].id).toBe('test2');
    expect(elements[2].id).toBe('test3');
  });
});

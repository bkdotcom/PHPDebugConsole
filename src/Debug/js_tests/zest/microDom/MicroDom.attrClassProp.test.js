import { default as zest } from '../../../js_src/zest/Zest.js';

describe('MicroDom attribute, class, & property methods', () => {
  let container;

  beforeEach(() => {
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <select id="sel" multiple>
          <option value="a" selected>a</option>
          <option value="b">b</option>
          <option value="c" selected>c</option>
        </select>
        <h1 id="header">Header</h1>
      </div>
    `;
    container = document.getElementById('root');
  });

  describe('attr method', () => {
    test('gets attribute value', () => {
      const $div = zest('#div2');
      expect($div.attr('data-test')).toBe('123');
    });

    test('removes attribute if null passed', () => {
      const $div = zest('#div2');
      $div.attr('class', null);
      expect($div.attr('class')).toBeNull();
    });

    test('sets attribute value', () => {
      const $div = zest('#div2');
      $div.attr('data-test', '456');
      expect($div.attr('data-test')).toBe('456');
    });

    test('special class key', () => {
      const $div = zest('#div1');
      $div.attr('class', ['new-class', 'another-class']);
      expect($div[0].className).toBe('new-class another-class');
    });

    test(('special html key'), () => {
      const $div = zest('#div1');
      $div.attr('html', '<b>hi</b>');
      expect($div[0].innerHTML).toBe('<b>hi</b>');
    });

    test('special text key', () => {
      const $div = zest('#div1');
      $div.attr('text', 'abc');
      expect($div[0].textContent).toBe('abc');
    });

    test('special style key', () => {
      const $div = zest('#div1');
      $div.attr('style', 'color:red');
      expect($div[0].getAttribute('style')).toContain('color');
    });

    test('set boolean attribute', () => {
      const $div = zest('#div1');
      $div.attr('checked', true);
      expect($div[0].getAttribute('checked')).toBe('checked');
    });

    test('get boolean attribute', () => {
      const $div = zest('#div1');
      $div.attr('checked', false);
      expect($div[0].getAttribute('checked')).toBeNull();
    });
  });

  test('removeAttr', () => {
    const $div = zest('#div1');
    $div.attr('data-x', 'y');
    $div.removeAttr('data-x');
    expect($div.attr('data-x')).toBeNull();
  });

  test('prop get/set, class alias', () => {
    const $div = zest('#div1');
    $div.prop('id', 'newid');
    expect($div.prop('id')).toBe('newid');

    $div.prop('class', 'abc');
    expect($div[0].className).toBe('abc');
    expect($div.prop('class')).toBe('abc');

    $div.prop({title: 'hi'});
    expect($div[0].title).toBe('hi');
  });

  describe('addClass method', () => {
    test('adds single class to element', () => {
      const $div = zest('#div1');
      $div.addClass('baz');
      expect($div[0].classList.contains('baz')).toBe(true);
    });

    test('accepts array', () => {
      const $div = zest('#div1');
      $div.addClass(['a', 'b']);
      expect($div[0].classList.contains('a')).toBe(true);
      expect($div[0].classList.contains('b')).toBe(true);
    });

    test('gets class from function', () => {
      const $div = zest('#div1');
      $div.addClass((el, i) => 'dynamic');
      expect($div[0].classList.contains('dynamic')).toBe(true);
    });

    test('accepts simple object', () => {
      const $div = zest('#div1');
      const spy = jest.spyOn($div, 'toggleClass');
      $div.addClass({foo: true});
      expect(spy).toHaveBeenCalledWith({foo: true});
      spy.mockRestore();
    });
  });

  describe('hasClass method', () => {
    test('returns true if element has class', () => {
      const $div = zest('#div1');
      expect($div.hasClass('foo')).toBe(true);
      expect($div.hasClass('bar')).toBe(true);
    })

    test ('returns false if element does not have class', () => {
      const $div = zest('#div1');
      expect($div.hasClass('baz')).toBe(false);
      expect($div.hasClass('non-existent')).toBe(false);
    })

    test('hasClass with multiple classes', () => {
      const $div = zest('#div1');
      $div.addClass('foo bar');
      expect($div.hasClass('foo bar')).toBe(true);
      expect($div.hasClass('foo baz')).toBe(false);
      expect($div.hasClass(['foo', 'bar'])).toBe(true);
    });
  });

  describe('removeClass method', () => {
    test('removes single class from element', () => {
      const $div = zest('#div1');
      $div.removeClass('bar');
      expect($div[0].classList.contains('bar')).toBe(false);
    })

    test('removes array from element', () => {
      const $div = zest('#div1');
      $div.removeClass(['foo', 'bar']);
      expect($div[0].className).toBe('');
    })

    test('gets classes from function', () => {
      const $div = zest('#div1');
      $div.addClass('baz');
      const fn = jest.fn((el, i) => 'baz');
      $div.removeClass(fn);
      expect(fn).toHaveBeenCalled();
      expect($div[0].classList.contains('baz')).toBe(false);
    });
  });

  describe('toggleClass method', () => {
    test('adds class if not present', () => {
      const $div = zest('#div1');
      $div.toggleClass('baz');
      expect($div[0].classList.contains('baz')).toBe(true);
    });

    test('removes class if present', () => {
      const $div = zest('#div1');
      $div.toggleClass('baz', true);
      expect($div[0].classList.contains('baz')).toBe(true);
    });

    test('toggles class based on 2nd param', () => {
      const $div = zest('#div1');
      $div.toggleClass('foo', false);
      expect($div[0].classList.contains('foo')).toBe(false);

      $div.toggleClass('foo', true);
      expect($div[0].classList.contains('foo')).toBe(true);
    });

    test('toggles object of classes', () => {
      const $div = zest('#div1');
      $div.toggleClass({
        baz: true,
        foo: false, // remove foo
        qux: false, // don't add qux
      });
      expect($div[0].classList.contains('baz')).toBe(true);
      expect($div[0].classList.contains('foo')).toBe(false);
      expect($div[0].classList.contains('qux')).toBe(false);
    });

    test('gets classes from function', () => {
      const $div = zest('#div1');
      const fn = jest.fn((el, i) => 'foo')
      $div.toggleClass(fn, true);
      expect(fn).toHaveBeenCalled();
      expect($div[0].classList.contains('foo')).toBe(true);
    })
  });

  describe('val method', () => {
    test('sets value for input', () => {
      const $input = zest('#div2 input');
      $input.val('new value');
      expect($input[0].value).toBe('new value');
    });

    test('gets value from input', () => {
      const $input = zest('#div2 input');
      expect($input.val()).toBe('abc');
    });

    test('sets value for checkbox', () => {
      const $checkbox = zest('#chk');

      $checkbox.val(false);
      expect($checkbox[0].checked).toBe(false);

      $checkbox.val(true);
      expect($checkbox[0].checked).toBe(true);
    });

    test('sets single option for select multiple', () => {
      const $select = zest('#sel');
      $select.val('b');
      expect($select[0].value).toBe('b');
    });

    test('sets multiple options for select multiple', () => {
      const $select = zest('#sel');
      $select.val(['b']);
      expect($select[0].value).toBe('b');
    });

    test('returns array for select multiple', () => {
      const $select = zest('#sel');
      expect($select.val()).toEqual(['a', 'c']);
    });

    test('returns undefined for empty', () => {
      const $none = zest('.doesnotexist');
      expect($none.val()).toBeUndefined();
    });
  });

});

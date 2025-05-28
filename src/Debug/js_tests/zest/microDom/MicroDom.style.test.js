import { default as zest } from '../../../js_src/zest/Zest.js';


describe('MicroDom style methods', () => {
  let container;

  beforeEach(() => {
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1" style="margin: 10px; padding: 5px; border: 1px solid black;">
          Hello <span class="child">World</span>
        </div>
        <div class="foo" id="div2" data-test="123">Test <input type="text" value="abc" /></div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;
    container = document.getElementById('root');

    // Mock getBoundingClientRect for consistent testing
    Element.prototype.getBoundingClientRect = jest.fn().mockImplementation(function() {
      return {
        width: 100,
        height: 50
      };
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe('style method', () => {

    test('addPx utility adds px to numeric values', () => {
      // Access the private addPx function by calling a method that uses it
      // and inspecting the style values it produces
      const $div = zest('#div2');

      // Test with numeric value (should add px)
      $div.height(100);
      expect($div[0].style.height).toBe('100px');

      // Test with string value that's a number (should add px)
      $div.height('200');
      expect($div[0].style.height).toBe('200px');

      // Test with non-numeric value (should not add px)
      $div.height('50%');
      expect($div[0].style.height).toBe('50%');

      // Test with value that already has px
      $div.height('300px');
      expect($div[0].style.height).toBe('300px');
    });

    test('returns style object with no args', () => {
      const $div = zest('#div1');
      const style = $div.style();
      expect(style).toBe($div[0].style);
      expect(style.margin).toBeTruthy();
    });

    test('sets style with string containing colon', () => {
      const $div = zest('#div1');
      $div.style('color: red; font-weight: bold');
      expect($div[0].style.color).toBe('red');
      expect($div[0].style.fontWeight).toBe('bold');
    });

    test('gets computed style for single property', () => {
      const $div = zest('#div1');
      expect($div.style('margin')).toBeTruthy();

      const $empty = zest('.non-existent');
      expect($empty.style('margin')).toBeUndefined();
    });

    test('sets style with property and value', () => {
      const $div = zest('#div1');
      $div.style('color', 'blue');
      expect($div[0].style.color).toBe('blue');
    });

    test('sets style with object', () => {
      const $div = zest('#div1');
      $div.style({
        color: 'green',
        fontSize: '12px'
      });
      expect($div[0].style.color).toBe('green');
      expect($div[0].style.fontSize).toBe('12px');
    });
  });

  describe('height methods', () => {
    test('height() gets content height', () => {
      const $div = zest('#div1');

      // Mock offsetHeight
      Object.defineProperty($div[0], 'offsetHeight', { value: 100 });

      // Call the method
      const height = $div.height();

      // Check result matches expected (offsetHeight - paddingY - borderY)
      expect(height).toBe(88); // 100 - 5*2 (padding) - 1*2 (border)
    });

    test('height(value) sets height', () => {
      const $div = zest('#div1');
      const styleSpy = jest.spyOn($div, 'style');

      $div.height(200);

      expect(styleSpy).toHaveBeenCalledWith('height', '200px');

      $div.height('50%');
      expect(styleSpy).toHaveBeenCalledWith('height', '50%');
    });

    test('innerHeight() gets client height', () => {
      const $div = zest('#div1');

      // Mock clientHeight
      Object.defineProperty($div[0], 'clientHeight', { value: 90 });

      expect($div.innerHeight()).toBe(90);

      // Test with empty collection
      const $empty = zest('.non-existent');
      expect($empty.innerHeight()).toBeUndefined();
    });

    test('innerHeight(value) sets height', () => {
      const $div = zest('#div1');
      const styleSpy = jest.spyOn($div, 'style');

      $div.innerHeight(150);

      expect(styleSpy).toHaveBeenCalledWith('height', '150px');
    });

    test('innerWidth() gets client width', () => {
      const $div = zest('#div1');

      // Mock clientWidth
      Object.defineProperty($div[0], 'clientWidth', { value: 120 });

      expect($div.innerWidth()).toBe(120);

      // Test with empty collection
      const $empty = zest('.non-existent');
      expect($empty.innerWidth()).toBeUndefined();
    });

    test('innerWidth(value) sets width', () => {
      const $div = zest('#div1');
      const styleSpy = jest.spyOn($div, 'style');

      $div.innerWidth(250);

      expect(styleSpy).toHaveBeenCalledWith('width', '250px');
    });

    test('outerHeight() gets offset height', () => {
      const $div = zest('#div1');

      // Mock offsetHeight
      Object.defineProperty($div[0], 'offsetHeight', { value: 100 });

      expect($div.outerHeight()).toBe(100);

      // Test with empty collection
      const $empty = zest('.non-existent');
      expect($empty.outerHeight()).toBeUndefined();
    });

    test('outerHeight(value, includeMargin=true) includes margins', () => {
      const $div = zest('#div1');

      // getBoundingClientRect was already mocked to return height: 50
      // Mock getComputedStyle for margins
      window.getComputedStyle = jest.fn().mockImplementation(() => ({
        marginTop: '10px',
        marginBottom: '10px'
      }));

      expect($div.outerHeight(undefined, true)).toBe(70); // 50 + 10 + 10
    });

    test('outerHeight(value) sets height', () => {
      const $div = zest('#div1');
      const styleSpy = jest.spyOn($div, 'style');

      $div.outerHeight(300);

      expect(styleSpy).toHaveBeenCalledWith('height', '300px');
    });
  });
});

import { default as zest } from '../../../js_src/zest/Zest.js';
import * as helper from '../../../js_src/zest/helper.js';

describe('MicroDom data methods', () => {
  let container;

  beforeEach(() => {
    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar" id="div1">Hello <span class="child">World</span></div>
        <div class="foo" id="div2" data-numeric="123" data-obj-value='{"key":"value"}'
             data-kebab-case="kebab" data-boolean="true" data-number="42">
          Test <input type="text" value="abc" />
        </div>
        <button id="btn" style="display:none">Click</button>
        <input type="checkbox" id="chk" checked />
        <h1 id="header">Header</h1>
      </div>
    `;
    container = document.getElementById('root');
  });

  describe('data method', () => {
    test('gets data attribute values with automatic type conversion', () => {
      const $div = zest('#div2');

      // String value
      expect($div.data('numeric')).toBe(123);

      // obj value should be automatically parsed
      expect($div.data('objValue')).toEqual({key: 'value'});

      // Boolean value should be converted
      expect($div.data('boolean')).toBe(true);

      // Number value should be converted
      expect($div.data('number')).toBe(42);

      // Kebab case should be converted to camelCase
      expect($div.data('kebabCase')).toBe('kebab');

      // Non-existent data should return undefined
      expect($div.data('nonExistent')).toBeUndefined();
    });

    test('sets data attributes with various value types', () => {
      const $div = zest('#div1');

      // Set string value
      $div.data('stringValue', 'hello');
      expect($div[0].dataset.stringValue).toBe('hello');

      // Set number value
      $div.data('numberValue', 123);
      expect($div[0].dataset.numberValue).toBe('123');
      expect($div.data('numberValue')).toBe(123);

      // Set boolean value
      $div.data('booleanValue', true);
      expect($div[0].dataset.booleanValue).toBe('true');
      expect($div.data('booleanValue')).toBe(true);

      // Set object value
      $div.data('objectValue', { prop: 'value' });
      expect($div[0].dataset.objectValue).toBeUndefined();
      expect($div.data('objectValue')).toEqual({ prop: 'value' });

      // Set array value
      $div.data('arrayValue', [1, 2, 3]);
      expect($div.data('arrayValue')).toEqual([1, 2, 3]);
    });

    test('handles kebab-case data attribute names', () => {
      const $div = zest('#div1');

      // Set with kebab case
      $div.data('kebab-case', 'value');
      expect($div[0].dataset.kebabCase).toBe('value');

      // Can retrieve with camelCase
      expect($div.data('kebabCase')).toBe('value');
    });

    test('sets multiple data values with an object', () => {
      const $div = zest('#div1');

      $div.data({
        prop1: 'value1',
        prop2: 123,
        'kebab-prop': true
      });

      expect($div[0].dataset.prop1).toBe('value1');
      expect($div[0].dataset.prop2).toBe('123');
      expect($div[0].dataset.kebabProp).toBe('true');

      expect($div.data('prop1')).toBe('value1');
      expect($div.data('prop2')).toBe(123);
      expect($div.data('kebabProp')).toBe(true);
    });

    test('handles non-serializable values by storing in special property', () => {
      const $div = zest('#div1');
      const circularObj = { prop: 'value' };
      circularObj.self = circularObj; // Create circular reference

      const functionValue = () => 'test';

      // Store non-serializable objects
      $div.data('circular', circularObj);
      $div.data('function', functionValue);

      // Verify they're stored in the special property
      expect($div[0][helper.rand].data.circular).toBe(circularObj);
      expect($div[0][helper.rand].data.function).toBe(functionValue);

      // Verify we can retrieve them
      expect($div.data('circular')).toBe(circularObj);
      expect($div.data('function')).toBe(functionValue);
      expect($div.data('function')()).toBe('test');
    });

    test('gets all data attributes when called without arguments', () => {
      const $div = zest('#div2');

      const allData = $div.data();

      expect(allData).toEqual({
        objValue: {key: 'value'},
        kebabCase: 'kebab',
        boolean: true,
        number: 42,
        numeric: 123,
      });
    });

    test('returns empty object when getting all data on empty collection', () => {
      const $empty = zest('.non-existent');
      expect($empty.data()).toEqual({});
    });

    test('returns undefined when getting specific data on empty collection', () => {
      const $empty = zest('.non-existent');
      expect($empty.data('test')).toBeUndefined();
    });

    test('merges dataset and non-serializable data when getting all data', () => {
      const $div = zest('#div1');
      const functionValue = () => 'test';

      // Set both regular and non-serializable data
      $div.data('regular', 'value');
      $div.data('special', functionValue);

      const allData = $div.data();

      expect(allData.regular).toBe('value');
      expect(allData.special).toBe(functionValue);
      expect(typeof allData.special).toBe('function');
    });

    test('handles malformed JSON in dataset', () => {
      const $div = zest('#div2');

      // Set malformed JSON that will cause JSON.parse to throw
      $div[0].dataset.badJson = '{not valid json}';

      // Should return the raw string instead of throwing
      expect($div.data('badJson')).toBe('{not valid json}');
    });

    test('handles undefined and null values', () => {
      const $div = zest('#div1');

      // Set undefined value - testing the return value is sufficient
      $div.data('undefinedValue', undefined);
      expect($div.data('undefinedValue')).toBe(undefined);

      // Set null value
      $div.data('nullValue', null);
      expect($div.data('nullValue')).toBe(null);
    });

    test('handles non-plain objects', () => {
      const $div = zest('#div1');

      // Set a Date object (non-plain object)
      const date = new Date();
      $div.data('dateValue', date);

      // Should be stored in the special property
      expect($div[0][helper.rand].data.dateValue).toBe(date);
      expect($div.data('dateValue')).toBe(date);

      // Set a RegExp object (non-plain object)
      const regex = /test/g;
      $div.data('regexValue', regex);

      // Should be stored in the special property
      expect($div[0][helper.rand].data.regexValue).toBe(regex);
      expect($div.data('regexValue')).toBe(regex);

      // Set a Map object (non-plain object)
      const map = new Map([['key', 'value']]);
      $div.data('mapValue', map);

      // Should be stored in the special property
      expect($div[0][helper.rand].data.mapValue).toBe(map);
      expect($div.data('mapValue')).toBe(map);
    });

    test('handles empty string values', () => {
      const $div = zest('#div1');

      // Set empty string
      $div.data('emptyString', '');
      expect($div[0].dataset.emptyString).toBe('');
      expect($div.data('emptyString')).toBe('');
    });

    test('properly handles values that are already strings', () => {
      const $div = zest('#div1');

      // Set a normal string
      $div.data('normalString', 'hello');
      expect($div[0].dataset.normalString).toBe('hello');

      // The empty branch in setValue for typeof value === 'string' should be covered
      expect($div.data('normalString')).toBe('hello');
    });
  });

  describe('removeData method', () => {
    test('removes a single data attribute', () => {
      const $div = zest('#div2');

      // Verify data exists initially
      expect($div.data('numeric')).toBe(123);

      // Remove the data
      $div.removeData('numeric');

      // Verify it's gone
      expect($div.data('numeric')).toBeUndefined();
    });

    test('removes non-serializable data from special property', () => {
      const $div = zest('#div1');
      const functionValue = () => 'test';

      // Set non-serializable data
      $div.data('special', functionValue);

      // Verify it's stored in the special property
      expect($div[0][helper.rand].data.special).toBe(functionValue);

      // Remove it
      $div.removeData('special');

      // Verify it's gone
      expect($div.data('special')).toBeUndefined();
      expect($div[0][helper.rand].data.special).toBeUndefined();
    });

    test('removes multiple data attributes when given an array', () => {
      const $div = zest('#div2');

      // Set multiple data attributes
      $div.data({
        prop1: 'value1',
        prop2: 'value2',
        prop3: 'value3'
      });

      // Remove multiple at once
      $div.removeData(['prop1', 'prop3']);

      // Verify correct ones were removed
      expect($div.data('prop1')).toBeUndefined();
      expect($div.data('prop2')).toBe('value2');
      expect($div.data('prop3')).toBeUndefined();
    });

    test('handles kebab-case names when removing data', () => {
      const $div = zest('#div2');

      // Set data with kebab-case
      $div.data('kebab-case', 'value');

      // Remove with kebab-case
      $div.removeData('kebab-case');

      // Verify it's gone
      expect($div.data('kebabCase')).toBeUndefined();
    });

    test('safely handles removal when helper.rand property does not exist', () => {
      const $div = zest('#div1');

      // Ensure the element doesn't have the helper.rand property
      delete $div[0][helper.rand];

      // This should not throw an error
      $div.removeData('anyProp');
    });

    test('works on multiple elements in collection', () => {
      const $elements = zest('#div1, #div2');

      // Set same data on both elements
      $elements.data('common', 'value');

      expect($elements[0].dataset.common).toBe('value');
      expect($elements[1].dataset.common).toBe('value');

      // Remove from all elements
      $elements.removeData('common');

      expect($elements[0].dataset.common).toBeUndefined();
      expect($elements[1].dataset.common).toBeUndefined();
    });

    test('removes data when passed a string with spaces', () => {
      const $div = zest('#div1');

      // Set multiple data values
      $div.data('test1', 'value1');
      $div.data('test2', 'value2');
      $div.data('test3', 'value3');

      // Remove using space-separated string
      $div.removeData('test1 test3');

      // Check only specified values were removed
      expect($div.data('test1')).toBeUndefined();
      expect($div.data('test2')).toBe('value2');
      expect($div.data('test3')).toBeUndefined();
    });

    test('handles empty strings in removeData', () => {
      const $div = zest('#div1');

      // Set data
      $div.data('test1', 'value1');

      // Call removeData with string containing spaces but no actual values
      $div.removeData('  ');

      // Data should remain unchanged
      expect($div.data('test1')).toBe('value1');
    });

    test('removes multiple data attributes with varied input formats', () => {
      const $div = zest('#div1');

      // Setup multiple data attributes
      $div.data({
        prop1: 'val1',
        prop2: 'val2',
        prop3: 'val3',
        prop4: 'val4'
      });

      // Remove using space-separated string
      $div.removeData('prop1 prop2');

      // Verify removal
      expect($div.data('prop1')).toBeUndefined();
      expect($div.data('prop2')).toBeUndefined();
      expect($div.data('prop3')).toBe('val3');

      // Remove using array
      $div.removeData(['prop3', 'prop4']);

      // Verify all data is now removed
      expect($div.data('prop3')).toBeUndefined();
      expect($div.data('prop4')).toBeUndefined();
    });
  });
});

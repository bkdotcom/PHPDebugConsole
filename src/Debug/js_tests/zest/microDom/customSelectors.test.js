import * as customSelectors from '../../../js_src/zest/microDom/customSelectors.js';

describe('MicroDom custom pseudo-selectors', () => {
  beforeEach(() => {
    // jsdom doesn't do visibility or display styles, so we need to register our own pseudo-selectors
    customSelectors.registerPseudoSelector('visible', el => el.classList.contains('vis'));
    customSelectors.registerPseudoSelector('hidden', el => el.classList.contains('hidden'));

    document.body.innerHTML = `
      <div id="root">
        <div class="foo bar vis" id="div1">Hello <span class="child">World</span></div>
        <div class="foo vis" id="div2" data-test="123">
          <input class="vis" type="text" id="text-input" value="abc" />
          <input type="password" id="password-input" />
          <input type="file" id="file-input" />
          <input type="radio" id="radio-input" />
          <input type="checkbox" id="checkbox-input" />
          <input type="search" id="search-input" />
          <input type="reset" id="reset-input" />
          <input type="button" id="button-input" value="Button" />
          <button class="vis" id="button-element">Button</button>
          <button type="reset" id="reset-button">Reset</button>
          <button type="submit" id="submit-button">Submit</button>
          <select id="select-element">
            <option value="1">Option 1</option>
            <option value="2" selected>Option 2</option>
            <option value="3">Option 3</option>
          </select>
          <textarea id="textarea-element">Text area</textarea>
        </div>
        <div class="foo hidden" id="div3" style="width: 0; height: 0; overflow: hidden;">
          Hidden div
        </div>
        <div class="foo hidden" id="div4" style="visibility: hidden;">
          Hidden with visibility
        </div>
        <button class="hidden" id="btn" style="display:none">Click</button>
        <h1 id="header1">Header 1</h1>
        <h2 id="header2">Header 2</h2>
        <h3 id="header3">Header 3</h3>
        <h4 id="header4">Header 4</h4>
        <h5 id="header5">Header 5</h5>
        <h6 id="header6">Header 6</h6>
      </div>
    `;
  });

  describe('Built-in pseudo-selectors', () => {
    test(':visible and :hidden selectors', () => {

      // Check visible elements
      const visibleElements = customSelectors.querySelectorAll(':visible');
      expect(visibleElements).toContain(document.getElementById('div1'));
      expect(visibleElements).toContain(document.getElementById('div2'));

      // Check hidden elements
      const hiddenElements = customSelectors.querySelectorAll(':hidden');
      expect(hiddenElements).toContain(document.getElementById('btn'));
      expect(hiddenElements).toContain(document.getElementById('div3'));
      expect(hiddenElements).toContain(document.getElementById('div4'));
    });

    test(':header selector', () => {
      const headers = customSelectors.querySelectorAll(':header');

      expect(headers.length).toBe(6);
      for (let i = 1; i <= 6; i++) {
        expect(headers).toContain(document.getElementById(`header${i}`));
      }
    });

    test(':input selector', () => {
      const inputs = customSelectors.querySelectorAll(':input');

      // Should select all form controls
      expect(inputs).toContain(document.getElementById('text-input'));
      expect(inputs).toContain(document.getElementById('password-input'));
      expect(inputs).toContain(document.getElementById('file-input'));
      expect(inputs).toContain(document.getElementById('button-element'));
      expect(inputs).toContain(document.getElementById('select-element'));
      expect(inputs).toContain(document.getElementById('textarea-element'));
    });

    test(':button selector', () => {
      const buttons = customSelectors.querySelectorAll(':button');

      expect(buttons).toContain(document.getElementById('button-input'));
      expect(buttons).toContain(document.getElementById('button-element'));
      expect(buttons).not.toContain(document.getElementById('text-input'));
    });

    test(':checkbox selector', () => {
      const checkboxes = customSelectors.querySelectorAll(':checkbox');

      expect(checkboxes).toContain(document.getElementById('checkbox-input'));
      expect(checkboxes).not.toContain(document.getElementById('radio-input'));
    });

    test(':file selector', () => {
      const files = customSelectors.querySelectorAll(':file');

      expect(files).toContain(document.getElementById('file-input'));
      expect(files).not.toContain(document.getElementById('text-input'));
    });

    test(':password selector', () => {
      const passwords = customSelectors.querySelectorAll(':password');

      expect(passwords).toContain(document.getElementById('password-input'));
      expect(passwords).not.toContain(document.getElementById('text-input'));
    });

    test(':radio selector', () => {
      const radios = customSelectors.querySelectorAll(':radio');

      expect(radios).toContain(document.getElementById('radio-input'));
      expect(radios).not.toContain(document.getElementById('checkbox-input'));
    });

    test(':reset selector', () => {
      const resets = customSelectors.querySelectorAll(':reset');

      expect(resets).toContain(document.getElementById('reset-input'));
      expect(resets).toContain(document.getElementById('reset-button'));
      expect(resets).not.toContain(document.getElementById('submit-button'));
    });

    test(':search selector', () => {
      const searches = customSelectors.querySelectorAll(':search');

      expect(searches).toContain(document.getElementById('search-input'));
      expect(searches).not.toContain(document.getElementById('text-input'));
    });

    test(':submit selector', () => {
      const submits = customSelectors.querySelectorAll(':submit');

      expect(submits).toContain(document.getElementById('submit-button'));
      expect(submits).not.toContain(document.getElementById('reset-button'));
    });

    test(':text selector', () => {
      const texts = customSelectors.querySelectorAll(':text');

      expect(texts).toContain(document.getElementById('text-input'));
      expect(texts).not.toContain(document.getElementById('password-input'));
    });

    test(':selected selector', () => {
      const selected = customSelectors.querySelectorAll(':selected');

      expect(selected.length).toBe(1);
      expect(selected[0]).toBe(document.querySelector('option[selected]'));
    });
  });

  describe('querySelectorAll and matches functions', () => {
    test('querySelectorAll with standard selector', () => {
      const divs = customSelectors.querySelectorAll('div', document);

      expect(divs.length).toBe(5); // div#root, div#div1, div#div2, div#div3, div#div4
      expect(divs[0].id).toBe('root');
      expect(divs[1].id).toBe('div1');
    });

    test('querySelectorAll with custom pseudo-selector', () => {
      const hidden = customSelectors.querySelectorAll(':hidden', document);

      expect(hidden).toContain(document.getElementById('btn'));
      expect(hidden).toContain(document.getElementById('div3'));
      expect(hidden).toContain(document.getElementById('div4'));
    });

    test('querySelectorAll with multiple selectors separated by commas', () => {
      const elements = customSelectors.querySelectorAll('h1, :button', document);

      expect(elements).toContain(document.getElementById('header1'));
      expect(elements).toContain(document.getElementById('button-element'));
      expect(elements).toContain(document.getElementById('button-input'));
    });

    test('querySelectorAll with scoped selector (>)', () => {
      const rootElement = document.getElementById('root');
      const directChildren = customSelectors.querySelectorAll('> div', rootElement);

      expect(directChildren.length).toBe(4); // div1, div2, div3, div4
      expect(directChildren[0].id).toBe('div1');
    });

    test('querySelectorAll with descendant combinator and custom pseudo', () => {
      const elements = customSelectors.querySelectorAll('#div2 :button', document);

      expect(elements).toContain(document.getElementById('button-input'));
      expect(elements).toContain(document.getElementById('button-element'));
    });

    test('querySelectorAll with sibling combinators', () => {
      // Adjacent sibling combinator
      const adjacent = customSelectors.querySelectorAll('#div1 + .foo', document);
      expect(adjacent.length).toBe(1);
      expect(adjacent[0].id).toBe('div2');

      // General sibling combinator
      const siblings = customSelectors.querySelectorAll('#div1 ~ .foo', document);
      expect(siblings.length).toBe(3);
      expect(siblings[0].id).toBe('div2');
      expect(siblings[1].id).toBe('div3');
      expect(siblings[2].id).toBe('div4');
    });

    test('matches with standard selector', () => {
      const div1 = document.getElementById('div1');

      expect(customSelectors.matches(div1, '.foo')).toBe(true);
      expect(customSelectors.matches(div1, '.bar')).toBe(true);
      expect(customSelectors.matches(div1, '.non-existent')).toBe(false);
    });

    test('matches with custom pseudo-selector', () => {
      const div3 = document.getElementById('div3');
      const div1 = document.getElementById('div1');

      expect(customSelectors.matches(div3, ':hidden')).toBe(true);
      expect(customSelectors.matches(div1, ':visible')).toBe(true);
      expect(customSelectors.matches(div1, ':hidden')).toBe(false);
    });

    test('matches with complex selector containing custom pseudo', () => {
      const button = document.getElementById('button-element');

      expect(customSelectors.matches(button, '#div2 :button')).toBe(true);
      expect(customSelectors.matches(button, '#div1 :button')).toBe(false);
    });
  });

  describe('registerPseudoSelector function', () => {
    test('register function-based pseudo-selector', () => {
      // Register a new pseudo-selector
      customSelectors.registerPseudoSelector('testSelector', (el) => {
        return el.id === 'div1';
      });

      // Test it works
      const elements = customSelectors.querySelectorAll(':testSelector');
      expect(elements.length).toBe(1);
      expect(elements[0].id).toBe('div1');

      // Test it works in matches
      expect(customSelectors.matches(document.getElementById('div1'), ':testSelector')).toBe(true);
      expect(customSelectors.matches(document.getElementById('div2'), ':testSelector')).toBe(false);
    });

    test('register string-based pseudo-selector', () => {
      // Register a new pseudo-selector that's replaced by a string
      customSelectors.registerPseudoSelector('divs', 'div');

      // Test it works
      const elements = customSelectors.querySelectorAll(':divs');
      expect(elements.length).toBeGreaterThan(0);
      elements.forEach(el => {
        expect(el.tagName.toLowerCase()).toBe('div');
      });
    });

    test('throws error for invalid selector type', () => {
      expect(() => {
        customSelectors.registerPseudoSelector('invalid', 123);
      }).toThrow('Filter must be a function or a string');
    });
  });

  describe('parseSelector function and complex selectors', () => {
    test('selector with multiple custom pseudo-selectors', () => {
      const visibleButtons = customSelectors.querySelectorAll(':visible:button');

      // Should only contain visible buttons
      expect(visibleButtons).toContain(document.getElementById('button-element'));
      expect(visibleButtons).not.toContain(document.getElementById('btn')); // Hidden button
    });

    test('selector with attribute selectors and pseudo-selectors', () => {
      const elements = customSelectors.querySelectorAll('input[type="text"]:visible');

      expect(elements).toContain(document.getElementById('text-input'));
    });

    test('selector with child combinator and pseudo-selector', () => {
      const elements = customSelectors.querySelectorAll('#div2 > :button');

      expect(elements).toContain(document.getElementById('button-element'));
      expect(elements).toContain(document.getElementById('button-input'));
    });

    test('selector with sibling combinator and pseudo-selector', () => {
      // Add test elements for sibling selectors
      const div2 = document.getElementById('div2');
      const spanAfterDiv2 = document.createElement('span');
      spanAfterDiv2.id = 'span-after-div2';
      spanAfterDiv2.className = 'vis';
      div2.parentNode.insertBefore(spanAfterDiv2, div2.nextSibling);

      const elements = customSelectors.querySelectorAll('#div2 + :visible');
      expect(elements).toContain(spanAfterDiv2);
    });

    test('duplicate elements are removed from results', () => {
      // Create a selector that could potentially return duplicates
      customSelectors.registerPseudoSelector('testDupe', 'div');

      const elements = customSelectors.querySelectorAll('div, :testDupe');

      // Count occurrences of each div
      const counts = {};
      elements.forEach(el => {
        if (el.tagName.toLowerCase() === 'div') {
          counts[el.id] = (counts[el.id] || 0) + 1;
        }
      });

      // Ensure no div appears more than once
      Object.values(counts).forEach(count => {
        expect(count).toBe(1);
      });
    });
  });
});

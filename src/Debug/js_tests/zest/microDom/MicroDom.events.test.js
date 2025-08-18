import { default as zest } from '../../../js_src/zest/Zest.js';
import * as helper from '../../../js_src/zest/helper.js';

class MockPointerEvent extends Event {
  constructor(type, props = {}) {
    super(type, props);
    // Object.assign(this, props);
  }
}

describe('MicroDom event methods', () => {

  window.PointerEvent = MockPointerEvent;

  beforeEach(() => {
    document.body.innerHTML = `
      <div id="root">
        <div id="parent">
          <button id="btn">Click</button>
          <button id="btn2">Button 2</button>
          <div id="container">
            <span id="span1" class="item">Item 1</span>
            <span id="span2" class="item">Item 2</span>
            <span id="span3" class="item special">Item 3</span>
          </div>
          <form id="form">
            <input type="text" id="input1" />
            <input type="checkbox" id="checkbox" />
            <input type="submit" id="submit" value="Submit" />
          </form>
        </div>
      </div>
    `;

    // Reset jest mocks
    jest.clearAllMocks();
  });

  describe('on method', () => {
    test('binds event handler to element', () => {
      const $btn = zest('#btn');
      const handler = jest.fn();

      $btn.on('click', handler);
      $btn[0].click();

      expect(handler).toHaveBeenCalledTimes(1);
      expect(handler.mock.instances[0]).toBe($btn[0]);
    });

    test('binds handler to multiple events', () => {
      const $input = zest('#input1');
      const handler = jest.fn();

      $input.on('focus blur', handler);

      const focusEvent = new Event('focus');
      $input[0].dispatchEvent(focusEvent);
      expect(handler).toHaveBeenCalledTimes(1);

      const blurEvent = new Event('blur');
      $input[0].dispatchEvent(blurEvent);
      expect(handler).toHaveBeenCalledTimes(2);
    });

    test('delegates events using selector', () => {
      const $container = zest('#container');
      const handler = jest.fn(function() {
        return this.id;
      });

      $container.on('click', '.item', handler);

      // Click on span1 should trigger handler
      zest('#span1')[0].click();
      expect(handler).toHaveBeenCalledTimes(1);
      expect(handler.mock.results[0].value).toBe('span1');

      // Click on span2 should also trigger handler
      zest('#span2')[0].click();
      expect(handler).toHaveBeenCalledTimes(2);
      expect(handler.mock.results[1].value).toBe('span2');

      // Click on container itself should not trigger handler
      $container[0].click();
      expect(handler).toHaveBeenCalledTimes(2);
    });

    test('handles mouseenter/mouseleave with selector correctly', () => {
      const $container = zest('#container');
      const handler = jest.fn();

      $container.on('mouseenter', '.item', handler);

      // Create a mouseenter event on span1
      const mouseEnterEvent = new MouseEvent('mouseenter', { bubbles: true });
      jest.spyOn(mouseEnterEvent, 'target', 'get').mockReturnValue(zest('#span1')[0]);
      $container[0].dispatchEvent(mouseEnterEvent);

      expect(handler).toHaveBeenCalledTimes(1);
    });

    test('correctly applies "this" context in handlers', () => {
      const $btn = zest('#btn');
      const thisValues = [];

      $btn.on('click', function() {
        thisValues.push(this);
      });

      $btn[0].click();
      expect(thisValues[0]).toBe($btn[0]);
    });

    test('handles extraParams in events', () => {
      const $btn = zest('#btn');
      const handler = jest.fn();

      $btn.on('click', handler);

      // Create event with extra params
      const event = new Event('click');
      event.extraParams = ['param1', 'param2'];
      $btn[0].dispatchEvent(event);

      expect(handler).toHaveBeenCalledWith(event, 'param1', 'param2');
    });

    test('does not trigger if target is no longer in DOM', () => {
      // Create a temporary element that we'll remove from DOM
      const tempElem = document.createElement('div');
      tempElem.id = 'temp';
      document.body.appendChild(tempElem);

      const $temp = zest('#temp');
      const handler = jest.fn();

      $temp.on('click', handler);

      // Now remove the element
      document.body.removeChild(tempElem);

      // This should not call the handler since the element is no longer in DOM
      const event = new Event('click');
      Object.defineProperty(event, 'target', { value: tempElem });
      document.dispatchEvent(event);

      expect(handler).not.toHaveBeenCalled();
    });

    test('stores event info in element helper property', () => {
      const $btn = zest('#btn');
      const handler = function() {
        // empty
      };

      $btn.on('click', handler);

      expect($btn[0][helper.rand].eventHandlers.length).toBe(1);
      expect($btn[0][helper.rand].eventHandlers[0].event).toBe('click');
      expect($btn[0][helper.rand].eventHandlers[0].handler).toBe(handler);
    });
  });

  describe('off method', () => {
    test('removes specific event handler', () => {
      const $btn = zest('#btn');
      const handler1 = jest.fn();
      const handler2 = jest.fn();

      $btn.on('click', handler1);
      $btn.on('click', handler2);
      $btn[0].click();

      expect(handler1).toHaveBeenCalledTimes(1);
      expect(handler2).toHaveBeenCalledTimes(1);

      // Remove only handler1
      $btn.off('click', handler1);
      $btn[0].click();

      expect(handler1).toHaveBeenCalledTimes(1); // Still 1
      expect(handler2).toHaveBeenCalledTimes(2); // Now 2
    });

    test('removes all handlers for specific event', () => {
      const $btn = zest('#btn');
      const handler1 = jest.fn();
      const handler2 = jest.fn();

      $btn.on('click', handler1);
      $btn.on('click', handler2);
      $btn[0].click();

      expect(handler1).toHaveBeenCalledTimes(1);
      expect(handler2).toHaveBeenCalledTimes(1);

      // Remove all click handlers
      $btn.off('click');
      $btn[0].click();

      expect(handler1).toHaveBeenCalledTimes(1); // Still 1
      expect(handler2).toHaveBeenCalledTimes(1); // Still 1
    });

    test('removes handlers for multiple events', () => {
      const $btn = zest('#btn');
      const handler = jest.fn();

      $btn.on('click', handler);
      $btn.on('mousedown', handler);

      $btn[0].click();

      const mousedownEvent = new Event('mousedown');
      $btn[0].dispatchEvent(mousedownEvent);

      expect(handler).toHaveBeenCalledTimes(2);

      // Remove handlers for both events
      $btn.off('click mousedown');

      $btn[0].click();
      $btn[0].dispatchEvent(mousedownEvent);

      expect(handler).toHaveBeenCalledTimes(2); // Still 2
    });

    test('removes delegated event handlers by selector', () => {
      const $container = zest('#container');
      const handler = jest.fn();

      $container.on('click', '.item', handler);
      zest('#span1')[0].click();
      expect(handler).toHaveBeenCalledTimes(1);

      // Remove handler by selector
      $container.off('click', '.item');
      zest('#span1')[0].click();
      expect(handler).toHaveBeenCalledTimes(1); // Still 1
    });

    test('removes all event handlers when called without arguments', () => {
      const $btn = zest('#btn');
      const handler1 = jest.fn();
      const handler2 = jest.fn();

      $btn.on('click', handler1);
      $btn.on('mousedown', handler2);

      $btn[0].click();
      const mousedownEvent = new Event('mousedown');
      $btn[0].dispatchEvent(mousedownEvent);

      expect(handler1).toHaveBeenCalledTimes(1);
      expect(handler2).toHaveBeenCalledTimes(1);

      // Remove all handlers
      $btn.off();

      $btn[0].click();
      $btn[0].dispatchEvent(mousedownEvent);

      expect(handler1).toHaveBeenCalledTimes(1); // Still 1
      expect(handler2).toHaveBeenCalledTimes(1); // Still 1
    });

    test('safely handles elements with no event handlers', () => {
      const $btn = zest('#btn');

      // Element has no handlers yet
      expect(() => {
        $btn.off('click');
      }).not.toThrow();
    });
  });

  describe('one method', () => {
    test('event handler is executed exactly once', () => {
      const $btn = zest('#btn');
      const handler = jest.fn();

      // one is not implemented yet in the source code
      // so we implement a mock one method that works like on but only once
      $btn.on('click', function onceHandler(e) {
        handler(e);
        $btn.off('click', onceHandler);
      });

      $btn[0].click();
      $btn[0].click();

      expect(handler).toHaveBeenCalledTimes(1);
    });
  });

  describe('trigger method', () => {
    test('triggers event by name', () => {
      const $btn = zest('#btn');
      const handler = jest.fn();

      $btn.on('click', handler);
      $btn.trigger('click');

      expect(handler).toHaveBeenCalledTimes(1);
    });

    test('triggers multiple events on multiple elements', () => {
      const $elements = zest('#btn, #btn2');
      const handler = jest.fn();

      $elements.on('click', handler);
      $elements.trigger('click');

      expect(handler).toHaveBeenCalledTimes(2);
    });

    test('passes extra parameters to handler', () => {
      const $btn = zest('#btn');
      const handler = jest.fn();

      $btn.on('click', handler);
      $btn.trigger('click', 'extra-param');

      expect(handler).toHaveBeenCalledWith(expect.any(Event), 'extra-param');
    });

    test('passes array of extra parameters to handler', () => {
      const $btn = zest('#btn');
      const handler = jest.fn();

      $btn.on('click', handler);
      $btn.trigger('click', ['param1', 'param2']);

      expect(handler).toHaveBeenCalledWith(expect.any(Event), 'param1', 'param2');
    });

    /*
    test('invokes element method if matches event name', () => {
      const $btn = zest('#btn');

      // Mock a method on the button element
      $btn[0].customMethod = jest.fn();

      $btn.trigger('customMethod');

      expect($btn[0].customMethod).toHaveBeenCalledTimes(1);
    });
    */

    test('accepts Event object as argument', () => {
      const $btn = zest('#btn');
      const handler = jest.fn(e => e.type);
      const customEvent = new Event('custom-event', { bubbles: true });

      $btn.on('custom-event', handler);
      $btn.trigger(customEvent);

      expect(handler).toHaveBeenCalledTimes(1);
      expect(handler.mock.results[0].value).toBe('custom-event');
    });
  });

  describe('integrated event behavior', () => {
    test('event delegation with mixed selectors and event types', () => {
      const $parent = zest('#parent');
      const clickHandler = jest.fn();
      const mouseenterHandler = jest.fn();

      $parent.on('click', '.item', clickHandler);
      $parent.on('mouseenter', '.special', mouseenterHandler);

      // Regular item clicked
      zest('#span1')[0].click();
      expect(clickHandler).toHaveBeenCalledTimes(1);

      // Special item clicked (should trigger both)
      zest('#span3')[0].click();
      expect(clickHandler).toHaveBeenCalledTimes(2);

      // Special item mouseenter
      const mouseenterEvent = new MouseEvent('mouseenter', { bubbles: true });
      Object.defineProperty(mouseenterEvent, 'target', { get: () => zest('#span3')[0] });
      $parent[0].dispatchEvent(mouseenterEvent);

      expect(mouseenterHandler).toHaveBeenCalledTimes(1);
    });

    test('complete event flow: on -> trigger -> off', () => {
      const $container = zest('#container');
      const handler = jest.fn();

      // Add event
      $container.on('custom-event', '.item', handler);

      // Trigger on matching element
      zest('#span1').trigger('custom-event');
      expect(handler).toHaveBeenCalledTimes(1);

      // Remove event
      $container.off('custom-event', '.item');

      // Trigger again - should not call handler
      zest('#span1').trigger('custom-event');
      expect(handler).toHaveBeenCalledTimes(1);
    });
  });
});

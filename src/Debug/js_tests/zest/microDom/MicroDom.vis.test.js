import { default as zest } from '../../../js_src/zest/Zest.js';
import * as helper from '../../../js_src/zest/helper.js';

describe('MicroDom visibility and animation methods', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="root">
        <button id="btn" style="display:none">Click</button>
        <div id="div1">Content</div>
        <div id="div2" style="padding: 10px; margin: 5px; height: 50px">Slide Content</div>
        <div id="div3" style="display: block">Toggle Test</div>
        <div id="div4" style="opacity: 0.5">Animate Test</div>
      </div>
    `;

    // Mock performance.now for animation tests
    /*
    if (!window.performance) {
      window.performance = {};
    }
    let nowValue = 0;
    window.performance.now = jest.fn(() => nowValue);

    // Expose for tests to advance the timer
    window.advancePerformanceTime = (ms) => {
      nowValue += ms;
    };

    // Mock requestAnimationFrame
    window.requestAnimationFrame = jest.fn(callback => {
      setTimeout(() => callback(performance.now()), 16); // ~60fps
    });
    */
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('show and hide methods', () => {
    test('hide() method hides elements', () => {
      const $div = zest('#div1');
      expect($div[0].style.display).toBe('');

      $div.hide();
      expect($div[0].style.display).toBe('none');
    });

    test('show() method shows hidden elements', () => {
      const $btn = zest('#btn');
      expect($btn[0].style.display).toBe('none');

      $btn.show();
      expect($btn[0].style.display).not.toBe('none');
    });

    test('show() restores original display value if stored', () => {
      const $div = zest('#div3');
      // Store the original display value
      helper.elInitMicroDomInfo($div[0]);
      $div[0][helper.rand] = { display: 'inline-block' };

      $div.hide();
      expect($div[0].style.display).toBe('none');

      $div.show();
      expect($div[0].style.display).toBe('inline-block');
    });
  });

  describe('toggle method', () => {
    test('toggle() toggles the display between none and original value', () => {
      const $div = zest('#div3');
      expect($div[0].style.display).toBe('block');

      $div.toggle();
      expect($div[0].style.display).toBe('none');

      $div.toggle();
      expect($div[0].style.display).not.toBe('none');
    });

    test('toggle(true) explicitly shows element', () => {
      const $btn = zest('#btn');
      expect($btn[0].style.display).toBe('none');

      $btn.toggle(true);
      expect($btn[0].style.display).not.toBe('none');

      // Toggle again with true should keep it shown
      $btn.toggle(true);
      expect($btn[0].style.display).not.toBe('none');
    });

    test('toggle(false) explicitly hides element', () => {
      const $div = zest('#div3');
      expect($div[0].style.display).toBe('block');

      $div.toggle(false);
      expect($div[0].style.display).toBe('none');

      // Toggle again with false should keep it hidden
      $div.toggle(false);
      expect($div[0].style.display).toBe('none');
    });
  });

  describe('fadeIn and fadeOut methods', () => {
    test('fadeIn() shows hidden element with opacity transition', () => {
      jest.useFakeTimers();
      const $btn = zest('#btn');
      expect($btn[0].style.display).toBe('none');

      // Start fadeIn with default duration
      $btn.fadeIn();

      // Element should have opacity 1 and transition set
      expect($btn[0].style.opacity).toBe('1');
      expect($btn[0].style.transition).toContain('opacity');
      expect($btn[0].style.transition).toContain('400ms');

      // After timeout completes, transition should be reset
      jest.advanceTimersByTime(400);
      expect($btn[0].style.display).not.toBe('none');
      expect($btn[0].style.transition).toBe('');

      jest.useRealTimers();
    });

    test('fadeIn() accepts "fast" and "slow" duration strings', () => {
      jest.useFakeTimers();
      const $div1 = zest('#div1');
      $div1.hide();

      $div1.fadeIn('fast');
      expect($div1[0].style.transition).toContain('200ms');
      jest.advanceTimersByTime(200);

      const $div2 = zest('#div2');
      $div2.hide();

      $div2.fadeIn('slow');
      expect($div2[0].style.transition).toContain('600ms');
      jest.advanceTimersByTime(600);

      jest.useRealTimers();
    });

    test('fadeOut() hides element with opacity transition', () => {
      jest.useFakeTimers();
      const $div = zest('#div1');
      expect($div[0].style.display).toBe('');

      // Start fadeOut with default duration
      $div.fadeOut();

      // Element should have opacity 0 and transition set
      expect($div[0].style.opacity).toBe('0');
      expect($div[0].style.transition).toContain('opacity');
      expect($div[0].style.transition).toContain('400ms');

      // After timeout completes, display should be none and transition reset
      jest.advanceTimersByTime(400);
      expect($div[0].style.display).toBe('none');
      expect($div[0].style.transition).toBe('');

      jest.useRealTimers();
    });

    test('fadeIn and fadeOut execute completion callbacks', () => {
      jest.useFakeTimers();
      const $div = zest('#div1');
      const fadeInCallback = jest.fn();
      const fadeOutCallback = jest.fn();

      // Test fadeIn callback
      $div.hide();
      $div.fadeIn(200, fadeInCallback);
      jest.advanceTimersByTime(200);
      expect(fadeInCallback).toHaveBeenCalledTimes(1);
      expect(fadeInCallback).toHaveBeenCalledWith($div[0]);

      // Test fadeOut callback
      $div.fadeOut(200, fadeOutCallback);
      jest.advanceTimersByTime(200);
      expect(fadeOutCallback).toHaveBeenCalledTimes(1);
      expect(fadeOutCallback).toHaveBeenCalledWith($div[0]);

      jest.useRealTimers();
    });
  });

  describe('slideDown and slideUp methods', () => {
    test('slideDown() shows element with height animation', () => {
      jest.useFakeTimers();
      const $div = zest('#div2');
      $div.hide();

      $div.slideDown();

      // Element should be visible with transition properties set
      expect($div[0].style.display).not.toBe('none');
      expect($div[0].style.transitionProperty).toBe('height, margin, padding');
      expect($div[0].style.transitionDuration).toBe('400ms');
      expect($div[0].style.height).not.toBe('');
      expect($div[0].style.overflow).toBe('hidden');

      // After animation completes, properties should be reset
      jest.advanceTimersByTime(400);
      expect($div[0].style.height).toBe('');
      expect($div[0].style.overflow).toBe('');
      expect($div[0].style.transitionDuration).toBe('');
      expect($div[0].style.transitionProperty).toBe('');

      jest.useRealTimers();
    });

    test('slideUp() hides element with height animation', () => {
      jest.useFakeTimers();
      const $div = zest('#div2');

      $div.slideUp();

      // Element should have transition properties set
      expect($div[0].style.transitionProperty).toBe('height, margin, padding');
      expect($div[0].style.transitionDuration).toBe('400ms');
      expect($div[0].style.height).toBe('0px');
      expect($div[0].style.overflow).toBe('hidden');

      // After animation completes, element should be hidden
      jest.advanceTimersByTime(400);
      expect($div[0].style.display).toBe('none');
      expect($div[0].style.height).toBe('');
      expect($div[0].style.overflow).toBe('');
      expect($div[0].style.transitionDuration).toBe('');
      expect($div[0].style.transitionProperty).toBe('');

      jest.useRealTimers();
    });

    test('slideDown and slideUp execute completion callbacks', () => {
      jest.useFakeTimers();
      const $div = zest('#div1');
      const slideDownCallback = jest.fn();
      const slideUpCallback = jest.fn();

      // Test slideDown callback
      $div.hide();
      $div.slideDown(200, slideDownCallback);
      jest.advanceTimersByTime(200);
      expect(slideDownCallback).toHaveBeenCalledTimes(1);
      expect(slideDownCallback).toHaveBeenCalledWith($div[0]);

      // Test slideUp callback
      $div.slideUp(200, slideUpCallback);
      jest.advanceTimersByTime(200);
      expect(slideUpCallback).toHaveBeenCalledTimes(1);
      expect(slideUpCallback).toHaveBeenCalledWith($div[0]);

      jest.useRealTimers();
    });
  });

  describe('animate method', () => {
    test('animate() changes CSS properties over time', () => {
      jest.useFakeTimers();

      const $div = zest('#div4');
      const originalOpacity = 0.5;
      const completeCallback = jest.fn();

      // Start animation
      $div.animate({ opacity: 1 }, 400, 'linear', completeCallback);

      // At start, nothing should have changed yet
      expect($div[0].style.opacity).toBe('0.5');

      // At 25% completion
      jest.advanceTimersByTime(100);
      jest.advanceTimersByTime(16);
      expect(parseFloat($div[0].style.opacity)).toBeGreaterThan(originalOpacity);
      expect(parseFloat($div[0].style.opacity)).toBeLessThan(1);

      // At 50% completion
      jest.advanceTimersByTime(100);
      jest.advanceTimersByTime(16);
      // expect(parseFloat($div[0].style.opacity)).toBeGreaterThan(0.6);

      // At 100% completion
      jest.advanceTimersByTime(200);
      jest.advanceTimersByTime(16);
      expect($div[0].style.opacity).toBe('1');
      expect(completeCallback).toHaveBeenCalledTimes(1);
      expect(completeCallback).toHaveBeenCalledWith($div[0]);

      jest.useRealTimers();
    });

    test('animate() handles multiple properties', () => {
      jest.useFakeTimers();

      const $div = zest('#div2');

      // Start animation with multiple properties
      $div.animate({
        opacity: 0.5,
        height: '100px',
        width: '200px'
      }, 200);

      // At 100% completion
      jest.advanceTimersByTime(200);
      jest.advanceTimersByTime(32); // Allow for requestAnimationFrame

      expect($div[0].style.opacity).toBe('0.5');
      expect($div[0].style.height).toBe('100px');
      expect($div[0].style.width).toBe('200px');

      jest.useRealTimers();
    });

    test('animate() supports different easing functions', () => {
      jest.useFakeTimers();

      const $div1 = zest('#div1');
      const $div2 = zest('#div2');
      const $div3 = zest('#div3');
      const $div4 = zest('#div4');

      $div1.animate({ opacity: 0 }, 200, 'linear');
      $div2.animate({ opacity: 0 }, 200, 'easeIn');
      $div3.animate({ opacity: 0 }, 200, 'easeOut');
      $div4.animate({ opacity: 0 }, 200, 'swing');

      // Different easing functions should produce different intermediate values
      jest.advanceTimersByTime(100); // 50% through animation
      jest.advanceTimersByTime(16);

      const opacities = [
        parseFloat($div1[0].style.opacity),
        parseFloat($div2[0].style.opacity),
        parseFloat($div3[0].style.opacity),
        parseFloat($div4[0].style.opacity)
      ];

      // Verify at least some of the values are different (different easing curves)
      const uniqueValues = new Set(opacities.map(o => o.toFixed(3)));
      expect(uniqueValues.size).toBeGreaterThan(1);

      jest.useRealTimers();
    });

    test('animate() handles custom easing function', () => {
      jest.useFakeTimers();

      const $div = zest('#div4');
      const customEasing = jest.fn(p => p * p * p); // Cubic easing

      $div.animate({ opacity: 0 }, 200, customEasing);

      // At 50% completion
      jest.advanceTimersByTime(100);
      jest.advanceTimersByTime(16);

      expect(customEasing).toHaveBeenCalled();

      jest.useRealTimers();
    });
  });

  test('all methods work together in sequence', () => {
    jest.useFakeTimers();
    const $btn = zest('#btn');

    // Test sequence: fadeIn -> fadeOut -> slideDown -> slideUp -> toggle -> animate
    $btn.fadeIn(100);
    jest.advanceTimersByTime(100);
    expect($btn[0].style.display).not.toBe('none');

    $btn.fadeOut(100);
    jest.advanceTimersByTime(100);
    expect($btn[0].style.display).toBe('none');

    $btn.slideDown(100);
    jest.advanceTimersByTime(100);
    expect($btn[0].style.display).not.toBe('none');

    $btn.slideUp(100);
    jest.advanceTimersByTime(100);
    expect($btn[0].style.display).toBe('none');

    $btn.toggle();
    expect($btn[0].style.display).not.toBe('none');

    $btn.toggle();
    expect($btn[0].style.display).toBe('none');

    // Animate
    const $div = zest('#div4');
    $div[0].style.opacity = 0;
    $div.animate({ opacity: 1 }, 100);
    jest.advanceTimersByTime(100);
    jest.advanceTimersByTime(16);
    expect($div[0].style.opacity).toBe('1');

    jest.useRealTimers();
  });
});

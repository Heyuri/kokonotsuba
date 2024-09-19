/*
 * <<<1 Smooth.js
 *
 * @see https://github.com/osuushi/Smooth.js
 */
/*
Smooth.js version 0.1.7

Turn arrays into smooth functions.

Copyright 2012 Spencer Cohen
Licensed under MIT license (see "Smooth.js MIT license.txt")
*/

/*Constants (these are accessible by Smooth.WHATEVER in user space)
*/

(function() {
  var AbstractInterpolator, CubicInterpolator, Enum, LinearInterpolator, NearestInterpolator, PI, SincFilterInterpolator, Smooth, clipClamp, clipMirror, clipPeriodic, defaultConfig, getColumn, getType, isValidNumber, k, makeLanczosWindow, makeScaledFunction, makeSincKernel, normalizeScaleTo, shallowCopy, sin, sinc, v, validateNumber, validateVector,
    __hasProp = Object.prototype.hasOwnProperty,
    __extends = function(child, parent) { for (var key in parent) { if (__hasProp.call(parent, key)) child[key] = parent[key]; } function ctor() { this.constructor = child; } ctor.prototype = parent.prototype; child.prototype = new ctor; child.__super__ = parent.prototype; return child; };

  Enum = {
    /*Interpolation methods
    */
    METHOD_NEAREST: 'nearest',
    METHOD_LINEAR: 'linear',
    METHOD_CUBIC: 'cubic',
    METHOD_LANCZOS: 'lanczos',
    METHOD_SINC: 'sinc',
    /*Input clipping modes
    */
    CLIP_CLAMP: 'clamp',
    CLIP_ZERO: 'zero',
    CLIP_PERIODIC: 'periodic',
    CLIP_MIRROR: 'mirror',
    /* Constants for control over the cubic interpolation tension
    */
    CUBIC_TENSION_DEFAULT: 0,
    CUBIC_TENSION_CATMULL_ROM: 0
  };

  defaultConfig = {
    method: Enum.METHOD_CUBIC,
    cubicTension: Enum.CUBIC_TENSION_DEFAULT,
    clip: Enum.CLIP_CLAMP,
    scaleTo: 0,
    sincFilterSize: 2,
    sincWindow: void 0
  };

  /*Index clipping functions
  */

  clipClamp = function(i, n) {
    return Math.max(0, Math.min(i, n - 1));
  };

  clipPeriodic = function(i, n) {
    i = i % n;
    if (i < 0) i += n;
    return i;
  };

  clipMirror = function(i, n) {
    var period;
    period = 2 * (n - 1);
    i = clipPeriodic(i, period);
    if (i > n - 1) i = period - i;
    return i;
  };

  /*
  Abstract scalar interpolation class which provides common functionality for all interpolators

  Subclasses must override interpolate().
  */

  AbstractInterpolator = (function() {

    function AbstractInterpolator(array, config) {
      this.array = array.slice(0);
      this.length = this.array.length;
      if (!(this.clipHelper = {
        clamp: this.clipHelperClamp,
        zero: this.clipHelperZero,
        periodic: this.clipHelperPeriodic,
        mirror: this.clipHelperMirror
      }[config.clip])) {
        throw "Invalid clip: " + config.clip;
      }
    }

    AbstractInterpolator.prototype.getClippedInput = function(i) {
      if ((0 <= i && i < this.length)) {
        return this.array[i];
      } else {
        return this.clipHelper(i);
      }
    };

    AbstractInterpolator.prototype.clipHelperClamp = function(i) {
      return this.array[clipClamp(i, this.length)];
    };

    AbstractInterpolator.prototype.clipHelperZero = function(i) {
      return 0;
    };

    AbstractInterpolator.prototype.clipHelperPeriodic = function(i) {
      return this.array[clipPeriodic(i, this.length)];
    };

    AbstractInterpolator.prototype.clipHelperMirror = function(i) {
      return this.array[clipMirror(i, this.length)];
    };

    AbstractInterpolator.prototype.interpolate = function(t) {
      throw 'Subclasses of AbstractInterpolator must override the interpolate() method.';
    };

    return AbstractInterpolator;

  })();

  NearestInterpolator = (function(_super) {

    __extends(NearestInterpolator, _super);

    function NearestInterpolator() {
      NearestInterpolator.__super__.constructor.apply(this, arguments);
    }

    NearestInterpolator.prototype.interpolate = function(t) {
      return this.getClippedInput(Math.round(t));
    };

    return NearestInterpolator;

  })(AbstractInterpolator);

  LinearInterpolator = (function(_super) {

    __extends(LinearInterpolator, _super);

    function LinearInterpolator() {
      LinearInterpolator.__super__.constructor.apply(this, arguments);
    }

    LinearInterpolator.prototype.interpolate = function(t) {
      var k;
      k = Math.floor(t);
      t -= k;
      return (1 - t) * this.getClippedInput(k) + t * this.getClippedInput(k + 1);
    };

    return LinearInterpolator;

  })(AbstractInterpolator);

  CubicInterpolator = (function(_super) {

    __extends(CubicInterpolator, _super);

    function CubicInterpolator(array, config) {
      this.tangentFactor = 1 - Math.max(0, Math.min(1, config.cubicTension));
      CubicInterpolator.__super__.constructor.apply(this, arguments);
    }

    CubicInterpolator.prototype.getTangent = function(k) {
      return this.tangentFactor * (this.getClippedInput(k + 1) - this.getClippedInput(k - 1)) / 2;
    };

    CubicInterpolator.prototype.interpolate = function(t) {
      var k, m, p, t2, t3;
      k = Math.floor(t);
      m = [this.getTangent(k), this.getTangent(k + 1)];
      p = [this.getClippedInput(k), this.getClippedInput(k + 1)];
      t -= k;
      t2 = t * t;
      t3 = t * t2;
      return (2 * t3 - 3 * t2 + 1) * p[0] + (t3 - 2 * t2 + t) * m[0] + (-2 * t3 + 3 * t2) * p[1] + (t3 - t2) * m[1];
    };

    return CubicInterpolator;

  })(AbstractInterpolator);

  sin = Math.sin, PI = Math.PI;

  sinc = function(x) {
    if (x === 0) {
      return 1;
    } else {
      return sin(PI * x) / (PI * x);
    }
  };

  makeLanczosWindow = function(a) {
    return function(x) {
      return sinc(x / a);
    };
  };

  makeSincKernel = function(window) {
    return function(x) {
      return sinc(x) * window(x);
    };
  };

  SincFilterInterpolator = (function(_super) {

    __extends(SincFilterInterpolator, _super);

    function SincFilterInterpolator(array, config) {
      SincFilterInterpolator.__super__.constructor.apply(this, arguments);
      this.a = config.sincFilterSize;
      if (!config.sincWindow) throw 'No sincWindow provided';
      this.kernel = makeSincKernel(config.sincWindow);
    }

    SincFilterInterpolator.prototype.interpolate = function(t) {
      var k, n, sum, _ref, _ref2;
      k = Math.floor(t);
      sum = 0;
      for (n = _ref = k - this.a + 1, _ref2 = k + this.a; _ref <= _ref2 ? n <= _ref2 : n >= _ref2; _ref <= _ref2 ? n++ : n--) {
        sum += this.kernel(t - n) * this.getClippedInput(n);
      }
      return sum;
    };

    return SincFilterInterpolator;

  })(AbstractInterpolator);

  getColumn = function(arr, i) {
    var row, _i, _len, _results;
    _results = [];
    for (_i = 0, _len = arr.length; _i < _len; _i++) {
      row = arr[_i];
      _results.push(row[i]);
    }
    return _results;
  };

  makeScaledFunction = function(f, baseScale, scaleRange) {
    var scaleFactor, translation;
    if (scaleRange.join === '0,1') {
      return f;
    } else {
      scaleFactor = baseScale / (scaleRange[1] - scaleRange[0]);
      translation = scaleRange[0];
      return function(t) {
        return f(scaleFactor * (t - translation));
      };
    }
  };

  getType = function(x) {
    return Object.prototype.toString.call(x).slice('[object '.length, -1);
  };

  validateNumber = function(n) {
    if (isNaN(n)) throw 'NaN in Smooth() input';
    if (getType(n) !== 'Number') throw 'Non-number in Smooth() input';
    if (!isFinite(n)) throw 'Infinity in Smooth() input';
  };

  validateVector = function(v, dimension) {
    var n, _i, _len;
    if (getType(v) !== 'Array') throw 'Non-vector in Smooth() input';
    if (v.length !== dimension) throw 'Inconsistent dimension in Smooth() input';
    for (_i = 0, _len = v.length; _i < _len; _i++) {
      n = v[_i];
      validateNumber(n);
    }
  };

  isValidNumber = function(n) {
    return (getType(n) === 'Number') && isFinite(n) && !isNaN(n);
  };

  normalizeScaleTo = function(s) {
    var invalidErr;
    invalidErr = "scaleTo param must be number or array of two numbers";
    switch (getType(s)) {
      case 'Number':
        if (!isValidNumber(s)) throw invalidErr;
        s = [0, s];
        break;
      case 'Array':
        if (s.length !== 2) throw invalidErr;
        if (!(isValidNumber(s[0]) && isValidNumber(s[1]))) throw invalidErr;
        break;
      default:
        throw invalidErr;
    }
    return s;
  };

  shallowCopy = function(obj) {
    var copy, k, v;
    copy = {};
    for (k in obj) {
      if (!__hasProp.call(obj, k)) continue;
      v = obj[k];
      copy[k] = v;
    }
    return copy;
  };

  Smooth = function(arr, config) {
    var baseDomainEnd, dimension, i, interpolator, interpolatorClass, interpolators, k, n, properties, smoothFunc, v;
    if (config == null) config = {};
    properties = {};
    config = shallowCopy(config);
    properties.config = shallowCopy(config);
    if (config.scaleTo == null) config.scaleTo = config.period;
    if (config.sincFilterSize == null) {
      config.sincFilterSize = config.lanczosFilterSize;
    }
    for (k in defaultConfig) {
      if (!__hasProp.call(defaultConfig, k)) continue;
      v = defaultConfig[k];
      if (config[k] == null) config[k] = v;
    }
    if (!(interpolatorClass = {
      nearest: NearestInterpolator,
      linear: LinearInterpolator,
      cubic: CubicInterpolator,
      lanczos: SincFilterInterpolator,
      sinc: SincFilterInterpolator
    }[config.method])) {
      throw "Invalid method: " + config.method;
    }
    if (config.method === 'lanczos') {
      config.sincWindow = makeLanczosWindow(config.sincFilterSize);
    }
    if (arr.length < 2) throw 'Array must have at least two elements';
    properties.count = arr.length;
    smoothFunc = (function() {
      var _i, _j, _len, _len2;
      switch (getType(arr[0])) {
        case 'Number':
          properties.dimension = 'scalar';
          if (Smooth.deepValidation) {
            for (_i = 0, _len = arr.length; _i < _len; _i++) {
              n = arr[_i];
              validateNumber(n);
            }
          }
          interpolator = new interpolatorClass(arr, config);
          return function(t) {
            return interpolator.interpolate(t);
          };
        case 'Array':
          properties.dimension = dimension = arr[0].length;
          if (!dimension) throw 'Vectors must be non-empty';
          if (Smooth.deepValidation) {
            for (_j = 0, _len2 = arr.length; _j < _len2; _j++) {
              v = arr[_j];
              validateVector(v, dimension);
            }
          }
          interpolators = (function() {
            var _results;
            _results = [];
            for (i = 0; 0 <= dimension ? i < dimension : i > dimension; 0 <= dimension ? i++ : i--) {
              _results.push(new interpolatorClass(getColumn(arr, i), config));
            }
            return _results;
          })();
          return function(t) {
            var interpolator, _k, _len3, _results;
            _results = [];
            for (_k = 0, _len3 = interpolators.length; _k < _len3; _k++) {
              interpolator = interpolators[_k];
              _results.push(interpolator.interpolate(t));
            }
            return _results;
          };
        default:
          throw "Invalid element type: " + (getType(arr[0]));
      }
    })();
    if (config.clip === 'periodic') {
      baseDomainEnd = arr.length;
    } else {
      baseDomainEnd = arr.length - 1;
    }
    config.scaleTo || (config.scaleTo = baseDomainEnd);
    properties.domain = normalizeScaleTo(config.scaleTo);
    smoothFunc = makeScaledFunction(smoothFunc, baseDomainEnd, properties.domain);
    properties.domain.sort();
    /*copy properties
    */
    for (k in properties) {
      if (!__hasProp.call(properties, k)) continue;
      v = properties[k];
      smoothFunc[k] = v;
    }
    return smoothFunc;
  };

  for (k in Enum) {
    if (!__hasProp.call(Enum, k)) continue;
    v = Enum[k];
    Smooth[k] = v;
  }

  Smooth.deepValidation = true;

  (typeof exports !== "undefined" && exports !== null ? exports : window).Smooth = Smooth;

}).call(this);

/*
 * 桃の缶詰
 *
 * Copyright 2019 akahuku, akahuku@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

(function (global) {
'use strict';

/*
 * consts
 */

const APP_NAME = 'momo';
const VERSION = '0.9.89';
const MOMO_URL = document.currentScript.src.substring(0, document.currentScript.src.lastIndexOf('/'));

/*
 * <<<1 utility functions
 */

function $ (id) {
	return typeof id == 'string' ? document.getElementById(id) : id;
}

function $t (node, content) {
	node = $(node);
	if (!node) return undefined;
	if (content != undefined) {
		if (node.nodeName.toLowerCase() == 'input' || node.nodeName.toLowerCase() == 'textarea') {
			node.value = content;
		}
		else {
			node.textContent = content;
		}
	}
	return node.textContent;
}

function $qs (selector, node) {
	return ($(node) || document).querySelector(selector);
}

function $qsa (selector, node) {
	return ($(node) || document).querySelectorAll(selector);
}

let lastLog = 0;
function conlog (message) {
	const now = Date.now();
	if (lastLog) {
		console.log('+' + ('          ' + (now - lastLog)).substr(-10) + 'ms ' + message);
	}
	else {
		console.log(message);
	}
	lastLog = now;
}

function empty (node) {
	node = $(node);
	if (!node) return;
	const r = document.createRange();
	r.selectNodeContents(node);
	r.deleteContents();
}

function transitionend (element, callback, backupMsec) {
	element = $(element);
	if (!element) {
		if (callback) {
			callback({
				type: 'transitionend-backup',
				target: null,
			});
		}
		return;
	}

	if (typeof backupMsec == 'undefined') {
		const s = window.getComputedStyle(element);
		backupMsec = ['transitionDuration', 'transitionDelay'].reduce((result, current) => {
			let re = /([-+]?(?:\d+\.\d+|\d+|\.\d+)(?:[eE][-+]?\d+)?)(ms|s)/.exec(s[current]);
			if (re) {
				if (re[2] == 'ms') {
					return result + parseFloat(re[1], 10);
				}
				else if (re[2] == 's') {
					return result + parseFloat(re[1], 10) * 1000;
				}
			}

			return result;
		}, 0);
	}

	let backupTimer;
	let handler = function handleTransitionEnd (e) {
		if (backupTimer) {
			clearTimeout(backupTimer);
			backupTimer = null;
		}
		if (element) {
			element.removeEventListener('transitionend', handleTransitionEnd);
		}
		if (callback) {
			callback(e);
		}
		handler = element = callback = e = null;
	};

	element.addEventListener('transitionend', handler);
	backupTimer = setTimeout(handler, backupMsec || 0, {
		type: 'transitionend-backup',
		target: element,
	});
}

function transitionendp (element, backupMsec) { /*returns promise*/
	return new Promise(resolve => transitionend(element, resolve, backupMsec));
}

function minmax (min, value, max) {
	return isNaN(value) || typeof value != 'number' ?
		min : Math.max(min, Math.min(value, max));
}

function docScrollTop () {
	return Math.max(
		document.documentElement.scrollTop,
		document.body.scrollTop);
}

function docScrollLeft () {
	return Math.max(
		document.documentElement.scrollLeft,
		document.body.scrollLeft);
}

function parsejson (fragment, defaultValue) {
	try { return JSON.parse(fragment) }
	catch (e) { return defaultValue }
}

function subset (array, start, length) {
	return typeof length == 'undefined' ?
		Array.prototype.slice.call(array, start) :
		Array.prototype.slice.call(array, start, start + length);
}

function getBoundingClientRect (element) {
	element = $(element);
	if (element) {
		const r = element.getBoundingClientRect();
		return {
			left: r.left,
			top: r.top,
			right: r.right,
			bottom: r.bottom,
			width: r.width,
			height: r.height
		};
	}
	else {
		return {
			left: 0,
			top: 0,
			right: 0,
			bottom: 0,
			width: 0,
			height: 0
		};
	}
}

function dumpBoundingClientRect (r, tag) {
	conlog(`${tag || 'rect'}: ${r.left},${r.top} - ${r.right},${r.bottom} (${r.width},${r.height})`);
}

function getEventPath (e) {
	let path;
	if (!e) {
		throw new Error('getEventPath: invalid argument #1');
	}
	if ('path' in e) {
		path = e.path;
	}
	else if ('composedPath' in e) {
		path = e.composedPath();
	}
	else {
		path = [];
		for (let p = e.target; p; p = p.parentNode) {
			tags.push(p);
		}
	}
	if (path) {
		const result = path
			.filter(a => 'nodeName' in a)
			.map(a => {
				let result = a.nodeName;
				if ('className' in a && a.className != '') {
					result += '.' + a.className.split(/\s+/).join('.');
				}
				if ('id' in a && a.id != '') {
					result += '#' + a.id;
				}
				return result;
			})
			.reverse()
			.join(' ')
			.toLowerCase();
		//conlog(`getEventPath: "${result}"`);
		return result + ' ';
	}
	else {
		return '';
	}
}

function sgn (value) {
	if (isNaN(value) || value === Infinity || value === -Infinity) return value;
	if (value < 0) return -1;
	if (value > 0) return 1;
	return 0;
}

/*
 * <<<1 classes
 */

function popup (target, options) {
	const IMAGE_UP = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAA8AAAAICAYAAAAm06XyAAAAQElEQVQY05XMwQ0AIAhD0ToC++9YRqgnL0YKNuFE/oMkVEdS7t+FcoANzyqgDR0wCitgHL6Ar/AGFklFBH6Xmdg1tm7Xheu+iwAAAABJRU5ErkJggg==';
	const transport = {
		get overlay () {return overlay},
		get panel () {return panel},
		get upArrow () {return upArrow},
		get style () {return style},
		get cre () {return cre},
		get emit () {return emit},
		get leave () {return leave}
	};

	let overlay, panel, upArrow;

	// utility functions
	function style (elm, s) {
		for (let i in s) if (i in elm.style) elm.style[i] = '' + s[i];
		return elm;
	}

	function cre (elm, name) {
		return elm.appendChild(document.createElement(name));
	}

	function emit () {
		const args = Array.prototype.slice.call(arguments);
		const obj = args.shift();
		const name = args.shift();
		if (!(name in obj) || typeof obj[name] != 'function') return;
		try { return obj[name].apply(null, args) }
		catch (err) {
			console.error(`${APP_NAME}: exception in popup#emit: ${err.stack}`);
		}
	}

	// dom manipulators
	function createOverlay () {
		let result;
		return style(result = cre(options.root || document.body, 'div'), {
			position: 'fixed',
			left: 0, top: 0, right: 0, bottom: 0,
			backgroundColor: 'rgba(0,0,0,.01)',
			zIndex: '1879048192'
		})
	}

	function createPanel () {
		let result;
		style(result = cre(options.root || document.body, 'div'), {
			position: 'fixed',
			backgroundColor: '#fff',
			color: '#333',
			padding: '16px',
			border: '1px solid #eee',
			borderRadius: '3px',
			boxShadow: '0 10px 6px -6px rgba(0,0,0,.5)',
			zIndex: '1879048193'
		});

		style(upArrow = cre(result, 'img'), {
			position: 'absolute',
			left: '0', top: '-8px'
		});
		upArrow.src = IMAGE_UP;

		return result;
	}

	//
	function init () {
		options || (options = {});

		overlay = createOverlay();
		overlay.className = 'momocan-popup-overlay';
		overlay.addEventListener('click', handleOverlayClick);

		panel = createPanel();
		panel.className = 'momocan-popup-panel';

		emit(options, 'createPanel', transport);

		const targetPos = getBoundingClientRect(target);
		// TODO: screen clipping
		style(panel, {
			left: Math.floor(targetPos.left) + 'px',
			top: Math.floor(targetPos.top + target.offsetHeight + 3) + 'px'
		});
		style(upArrow, {
			left: (Math.min(panel.offsetWidth, target.offsetWidth) / 2 - 7) + 'px'
		});
	}

	function leave () {
		overlay.removeEventListener('click', handleOverlayClick);
		emit(options, 'close', transport);

		panel.parentNode.removeChild(panel);
		overlay.parentNode.removeChild(overlay);
		target.focus();
	}

	function handleOverlayClick (e) {
		e.preventDefault();
		emit(options, 'cancel', transport);
		leave();
	}

	init();
}

function colorPicker (target, options) {
	const IMAGE_SV = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAA8AAAAPAgMAAABGuH3ZAAAACVBMVEUAAAAAAAD///+D3c/SAAAAAXRSTlMAQObYZgAAADdJREFUCNdjYGB1YGBgiJrCwMC4NJOBgS1AzIFBkoFxAoRIYXVIYUhhAxIgFkICrA6kA6IXbAoAsj4LrV7uPHgAAAAASUVORK5CYII=';
	const IMAGE_HUE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAC4AAAAJCAYAAABNEB65AAAAR0lEQVQ4y9XTIQ4AMAhD0V/uf2emJkZCZlsUIYgnWoAmb7rukoQGqHlIQE+4O/6x1e/BEb3BZQjXDy7jqGiDK6CcSinkmvkDtwYMCcTVwlUAAAAASUVORK5CYII=';
	const LRU_KEY = 'momocan_colorpicker_LRU';
	const LRU_COLOR_ATTR = 'data-color';

	const DEDICATED_COLORS = {
		tier1: ['#000','#111','#222','#333','#444','#555','#666','#777','#f00','#0f0','#00f','#800000'],
		tier2: ['#888','#999','#aaa','#bbb','#ccc','#ddd','#eee','#fff','#0ff','#f0f','#ff0','#f0e0d6']
	};

	const event = createEventRegisterer();
	let transport;
	let colorPanel, LRUPanel, controlPanel,
		svCanvas, hueCanvas, receiver, colorText, okButton,
		svCursor, hueCursor, currentColor,
		palettePanel, currentPalette;

	function paintPalette (canvas) {
		const c = canvas.getContext('2d');
		c.fillStyle = '#000';
		c.fillRect(0, 0, canvas.width, canvas.height);
		for (let y = 0; y < 12; y++) {
			for (let x = 0; x < 20; x++) {
				const {left, top, width, height, color} = getPalette(x, y);
				c.fillStyle = color;
				c.fillRect(left, top, width, height);
			}
		}
	}

	function paintSaturationValue (canvas, hueValue) {
		const c = canvas.getContext('2d');
		c.clearRect(0, 0, canvas.width, canvas.height);

		let g;
		g = c.createLinearGradient(0, 0, canvas.width, 0);
		g.addColorStop(0, `hsl(${hueValue},100%,100%)`);
		g.addColorStop(1, `hsl(${hueValue},100%, 50%)`);
		c.fillStyle = g;
		c.fillRect(0, 0, canvas.width, canvas.height);

		g = c.createLinearGradient(0, 0, 0, canvas.height);
		g.addColorStop(0, `hsla(${hueValue},100%,50%,0)`);
		g.addColorStop(1, `hsla(${hueValue},100%, 0%,1)`);
		c.fillStyle = g;
		c.fillRect(0, 0, canvas.width, canvas.height);
	}

	function paintHue (canvas) {
		const c = canvas.getContext('2d');
		const g = c.createLinearGradient(0, 0, 0, canvas.height);
		g.addColorStop(0,         'hsl(  0,100%,50%)');
		g.addColorStop(1 / 6 * 1, 'hsl( 60,100%,50%)');
		g.addColorStop(1 / 6 * 2, 'hsl(120,100%,50%)');
		g.addColorStop(1 / 6 * 3, 'hsl(180,100%,50%)');
		g.addColorStop(1 / 6 * 4, 'hsl(240,100%,50%)');
		g.addColorStop(1 / 6 * 5, 'hsl(300,100%,50%)');
		g.addColorStop(1,         'hsl(360,100%,50%)');
		c.fillStyle = g;
		c.fillRect(0, 0, canvas.width, canvas.height);
	}

	function paintHexText (color) {
		colorText.value = color.text;
	}

	function paintHueCursor (color) {
		transport.style(hueCursor, {
			left: (hueCanvas.offsetLeft - 7) + 'px',
			top: (hueCanvas.offsetTop - 4 + (color.hue / 360) * hueCanvas.offsetHeight) + 'px'
		});
	}

	function paintSvCursor (color) {
		transport.style(svCursor, {
			left: (svCanvas.offsetLeft - 7 + color.saturation * (svCanvas.offsetWidth - 1)) + 'px',
			top: (svCanvas.offsetTop - 7 + (1 - color.value) * (svCanvas.offsetHeight - 1)) + 'px'
		});
	}

	function paintLRU () {
		let list = parsejson(window.localStorage[LRU_KEY]);
		if (!(list instanceof Array)) list = [];

		function setColor (node, color) {
			node.style.backgroundColor = color;
			node.setAttribute(LRU_COLOR_ATTR, color);
		}

		list.forEach(function (color, i) {
			if (LRUPanel.children[i]) {
				setColor(LRUPanel.children[i], color);
			}
		});

		// futaba specific
		//setColor(LRUPanel.children[LRUPanel.children.length - 2], '#800000');
		//setColor(LRUPanel.children[LRUPanel.children.length - 1], '#f0e0d6');
	}

	function paintPaletteElement (palette, isActive) {
		const c = palettePanel.getContext('2d');
		c.fillStyle = isActive ? '#ffffff' : '#000000';
		c.fillRect(
			palette.left - 1, palette.top - 1,
			palette.width + 2, palette.height + 2);
		c.fillStyle = palette.color;
		c.fillRect(
			palette.left, palette.top,
			palette.width, palette.height);
	}

	// event handlers
	function handleColorTextInput (e) {
		if (!/^#[0-9a-f]{6}$/i.test(e.target.value)) return;
		const color = parseHexColor(e.target.value);
		if (!color) return;

		currentColor = color;
		updateHSV(currentColor);
		paintSaturationValue(svCanvas, currentColor.hue);
		paintHueCursor(currentColor);
		paintSvCursor(currentColor);
		paintHexText(currentColor);
		transport.emit(options, 'change', currentColor);
	}

	function handleLRUPanelClick (e) {
		if (!e.target.hasAttribute(LRU_COLOR_ATTR)) return;
		colorText.value = e.target.getAttribute(LRU_COLOR_ATTR);
		handleColorTextInput({target: colorText});
	}

	function handleOkButtonClick (e) {
		transport.emit(options, 'ok', currentColor);
		pushLRU(currentColor.text);
		transport.leave();
	}

	function handleReceiverMousedown (e) {
		const x = e.offsetX;
		const y = e.offsetY;
		if (x >= svCanvas.offsetLeft && x < svCanvas.offsetLeft + svCanvas.offsetWidth
		&&  y >= svCanvas.offsetTop  && y < svCanvas.offsetTop  + svCanvas.offsetHeight) {
			e.target.addEventListener('pointermove', handleReceiverMousemove1);
			e.target.addEventListener('pointerup', handleReceiverMouseup);
			e.target.setPointerCapture(e.pointerId);
			e.preventDefault();
			handleReceiverMousemove1(e);
		}
		else if (x >= hueCanvas.offsetLeft && x < hueCanvas.offsetLeft + hueCanvas.offsetWidth
		&&       y >= hueCanvas.offsetTop  && y < hueCanvas.offsetTop  + hueCanvas.offsetHeight) {
			e.target.addEventListener('pointermove', handleReceiverMousemove2);
			e.target.addEventListener('pointerup', handleReceiverMouseup);
			e.target.setPointerCapture(e.pointerId);
			e.preventDefault();
			handleReceiverMousemove2(e);
		}
	}

	function handleReceiverMousemove1 (e) {
		if ('buttons' in e && !e.buttons) return handleReceiverMouseup(e);
		const x = e.offsetX - svCanvas.offsetLeft;
		const y = e.offsetY - svCanvas.offsetTop;
		currentColor.saturation = minmax(0, x / (svCanvas.offsetWidth - 1), 1.0);
		currentColor.value = 1.0 - minmax(0, y / (svCanvas.offsetHeight - 1), 1.0);
		updateRGB(currentColor);
		paintSvCursor(currentColor);
		paintHexText(currentColor);
		transport.emit(options, 'change', currentColor);
	}

	function handleReceiverMousemove2 (e) {
		if ('buttons' in e && !e.buttons) return handleReceiverMouseup(e);
		const x = e.offsetX - hueCanvas.offsetLeft;
		const y = e.offsetY - hueCanvas.offsetTop;
		currentColor.hue = minmax(0, y / hueCanvas.offsetHeight * 360, 359);
		paintSaturationValue(svCanvas, currentColor.hue);
		updateRGB(currentColor);
		paintHueCursor(currentColor);
		paintHexText(currentColor);
		transport.emit(options, 'change', currentColor);
	}

	function handleReceiverMouseup (e) {
		e.target.removeEventListener('pointermove', handleReceiverMousemove1);
		e.target.removeEventListener('pointermove', handleReceiverMousemove2);
		e.target.removeEventListener('pointerup', handleReceiverMouseup);
		e.target.releasePointerCapture(e.pointerId);
	}

	function handlePalettePanelClick (e) {
		currentColor = parseHexColor(getPalette(e).color);
		updateHSV(currentColor);
		paintSaturationValue(svCanvas, currentColor.hue);
		paintHueCursor(currentColor);
		paintSvCursor(currentColor);
		paintHexText(currentColor);
		transport.emit(options, 'change', currentColor);
	}

	function handlePalettePanelPointermove (e) {
		const palette = getPalette(e);

		if (currentPalette) {
			if (currentPalette.left == palette.left && currentPalette.top == palette.top) return;
			paintPaletteElement(currentPalette, false);
		}

		paintPaletteElement(palette, true);
		currentPalette = palette;
	}

	function handlePalettePanelMouseleave (e) {
		if (currentPalette) {
			paintPaletteElement(currentPalette, false);
			currentPalette = undefined;
		}
	}

	// core functions
	function parseHexColor (color) {
		let r = 255, g = 255, b = 255, re, result;
		re = /^\s*#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})\s*$/i.exec(color);
		if (re) {
			r = parseInt(re[1], 16);
			g = parseInt(re[2], 16);
			b = parseInt(re[3], 16);
		}
		else {
			re = /^\s*#?([0-9a-f])([0-9a-f])([0-9a-f])\s*$/i.exec(color)
			if (re) {
				r = parseInt(re[1], 16) * 17;
				g = parseInt(re[2], 16) * 17;
				b = parseInt(re[3], 16) * 17;
			}
		}
		if (re) {
			result = {
				hue: 0, saturation: 0, value: 0,
				r: r, g: g, b: b,
				text: ''
			};
			updateHSV(result);
			return result;
		}
		return null;
	}

	function updateRGB (color) {
		// @see https://en.wikipedia.org/wiki/HSL_and_HSV#From_HSV
		let C = color.value * color.saturation,
			Hd = color.hue / 60,
			X = C * (1 - Math.abs(Hd % 2 - 1)),
			m = color.value - C,
			R1, G1, B1;

		if      (0 <= Hd && Hd < 1) { R1 = C; G1 = X; B1 = 0; }
		else if (1 <= Hd && Hd < 2) { R1 = X; G1 = C; B1 = 0; }
		else if (2 <= Hd && Hd < 3) { R1 = 0; G1 = C; B1 = X; }
		else if (3 <= Hd && Hd < 4) { R1 = 0; G1 = X; B1 = C; }
		else if (4 <= Hd && Hd < 5) { R1 = X; G1 = 0; B1 = C; }
		else if (5 <= Hd && Hd < 6) { R1 = C; G1 = 0; B1 = X; }

		color.r = (minmax(0.0, R1 + m, 1.0) * 255).toFixed(0) - 0;
		color.g = (minmax(0.0, G1 + m, 1.0) * 255).toFixed(0) - 0;
		color.b = (minmax(0.0, B1 + m, 1.0) * 255).toFixed(0) - 0;
		updateHexText(color);
	}

	function updateHSV (color) {
		// @see https://en.wikipedia.org/wiki/HSL_and_HSV#Hue_and_chroma
		// @see https://en.wikipedia.org/wiki/HSL_and_HSV#Lightness
		// @see https://en.wikipedia.org/wiki/HSL_and_HSV#Saturation
		let r = color.r / 255, g = color.g / 255, b = color.b / 255,
			M = Math.max(r, g, b), m = Math.min(r, g, b), C = M - m, Hd;

		if      (C == 0) Hd = 0;
		else if (M == r) Hd = ((g - b) / C) % 6;
		else if (M == g) Hd = ((b - r) / C) + 2;
		else if (M == b) Hd = ((r - g) / C) + 4;

		color.hue = (60 * Hd + 360) % 360;
		color.value = M;
		color.saturation = minmax(0.0, C == 0 ? 0 : C / color.value, 1.0);
		updateHexText(color);
	}

	function updateHexText (color) {
		color.text = '#' +
			('00' + color.r.toString(16)).substr(-2) +
			('00' + color.g.toString(16)).substr(-2) +
			('00' + color.b.toString(16)).substr(-2);
	}

	function pushLRU (color) {
		let list = window.localStorage[LRU_KEY];
		try {
			list = parsejson(list);
			if (!(list instanceof Array)) list = [];

			let i = 0;
			for (; i < list.length; i++) {
				if (list[i] == color) {
					list.splice(i, 1);
					list.unshift(color);
					break;
				}
			}

			if (i >= list.length) {
				list.length >= LRUPanel.children.length && list.pop();
				list.unshift(color);
			}
		}
		finally {
			window.localStorage[LRU_KEY] = JSON.stringify(list);
		}
	}

	function getPalette (x, y) {
		if (typeof arguments[0] == 'object'
		&&  'offsetX' in arguments[0]
		&&  'offsetY' in arguments[0]) {
			const e = arguments[0];
			let ox = e.offsetX;
			let oy = e.offsetY;

			/*
			 * 0               14              28 30
			 * + ------------- + ------------- ++ -----------
			 *      - 13 -           - 13 -          - 11 -
			 */

			if (ox >= 30) {
				ox = Math.floor((ox - 30) / 12) + 2;
			}
			else if (ox >= 15) {
				ox = 1;
			}
			else {
				ox = 0;
			}

			x = Math.max(0, ox);
			y = Math.max(0, Math.floor((oy - 1) / 5));
		}

		switch (x) {
		case 0:
			return {
				left: 1 + 0 * 14,
				top: 1 + y * 5,
				width: 13,
				height: 4,
				color: DEDICATED_COLORS.tier1[y]
			};
			break;
		case 1:
			return {
				left: 1 + 1 * 14,
				top: 1 + y * 5,
				width: 13,
				height: 4,
				color: DEDICATED_COLORS.tier2[y]
			};
			break;
		default:
			x -= 2;
			return {
				left: 1 + 2 * 14 + 1 + x * 12,
				top: 1 + y * 5,
				width: 11,
				height: 4,
				color: '#' + [
					Math.floor(x / 6) * 3 + Math.floor(y / 6) * 9,
					x % 6 * 3,
					y % 6 * 3
				].map(v => v.toString(16)).join('')
			};
			break;
		}
	}

	let base = popup(target, {
		createPanel: t => {
			transport = t;
			const {style, cre} = t;

			// row 1, color panel
			colorPanel = cre(t.panel, 'div');
			style(svCanvas = cre(colorPanel, 'canvas'), {
				margin: '0 14px 0 0',
				width: '200px',
				height: '200px',
				outline: '1px solid silver'
			});
			svCanvas.width = 200;
			svCanvas.height = 200;

			style(hueCanvas = cre(colorPanel, 'canvas'), {
				margin: '0',
				width: '32px',
				height: '200px',
				outline: '1px solid silver'
			});
			hueCanvas.width = 32;
			hueCanvas.height = 200;

			style(svCursor = cre(colorPanel, 'img'), {
				position: 'absolute',
				left: '0',
				top: '0'
			});
			svCursor.src = IMAGE_SV;

			style(hueCursor = cre(colorPanel, 'img'), {
				position: 'absolute',
				left: '-5px',
				top: '0'
			});
			hueCursor.src = IMAGE_HUE;

			style(receiver = cre(colorPanel, 'div'), {
				position: 'absolute',
				width: '281px', height: '220px',
				left: '0', top: '0',
				backgroundColor: 'rgba(0,0,0,.01)'
			});

			// row 2, palette panel
			// x * 20 + 19 = 244
			// x * 20 = 225
			// 13 * 2 + 11 * 18 + 19 + 1 = 244
			//
			// 4 * 12 + 11 = 59
			//
			style(palettePanel = cre(t.panel, 'canvas'), {
				margin: '0',
				width: '246px',
				height: '61px',
				backgroundColor: '#000',
				outline: '1px solid silver'
			});
			palettePanel.width = 246;
			palettePanel.height = 61;

			// row 3, LRU panel
			style(LRUPanel = cre(t.panel, 'div'), {
				margin: '8px 0 8px 0',
				padding: '0 0 0 3px',
				width: '246px',
				overflow: 'hidden',
				whiteSpace: 'nowrap'
			});
			for (let i = 0; i < 9; i++) {
				style(cre(LRUPanel, 'div'), {
					display: 'inline-block',
					width: '22px',
					height: '22px',
					backgroundColor: '#808080',
					margin: '0 3px 0 0',
					border: '1px solid silver',
					cursor: 'pointer'
				});
			}

			// row 4, control panel
			style(controlPanel = cre(t.panel, 'div'), {
				margin: '0',
				textAlign: 'right'
			});

			style(colorText = cre(controlPanel, 'input'), {
				margin: '0 4px 0 0',
				pading: '3px',
				border: '1px solid silver',
				width: '8em',
				fontFamily: 'monospace'
			});
			colorText.type = 'text';
			colorText.maxLength = 7;

			style(okButton = cre(controlPanel, 'input'), {
				width: '8em'
			});
			okButton.type = 'button';
			okButton.value = 'OK';

			// draw gadgets

			currentColor = parseHexColor(options.initialColor || '#fff');
			paintHue(hueCanvas);
			paintSaturationValue(svCanvas, currentColor.hue);
			paintSvCursor(currentColor);
			paintHueCursor(currentColor);
			paintHexText(currentColor);
			paintLRU();
			paintPalette(palettePanel);

			// attach event handlers

			event
				.add(LRUPanel, 'click', handleLRUPanelClick)
				.add(colorText, 'input', handleColorTextInput)
				.add(okButton, 'click', handleOkButtonClick)
				.add(receiver, 'pointerdown', handleReceiverMousedown)
				.add(palettePanel, 'click', handlePalettePanelClick)
				.add(palettePanel, 'pointermove', handlePalettePanelPointermove)
				.add(palettePanel, 'mouseleave', handlePalettePanelMouseleave);
		},
		cancel: t => {
			transport.emit(options, 'cancel');
		},
		close: t => {
			transport.emit(options, 'close');
			event.removeAll();
			base = transport = null;
		}
	});
}

function popupMenu (target, options) {
	const event = createEventRegisterer();
	let transport;

	function handleMenuClick (e) {
		let p = e.target;
		for (; p; p = p.parentNode) {
			if (p.nodeName.toLowerCase() == 'a') {
				break;
			}
		}
		if (!p) return;

		e.preventDefault();
		transport.emit(options, 'ok', p);
		transport.leave();
	}

	let base = popup(target, {
		createPanel: t => {
			transport = t;
			const {style, cre} = t;

			const div = cre(t.panel, 'div');
			div.className = 'popup-menu-wrap';

			for (const item of options.items) {
				const anchor = cre(div, 'a');
				anchor.href = item.href;
				anchor.textContent = item.text;
			}

			event
				.add(div, 'click', handleMenuClick);
		},
		cancel: t => {
			transport.emit(options, 'cancel');
		},
		close: t => {
			transport.emit(options, 'close');
			event.removeAll();
			base = transport = null;
		}
	});
}

function createEventRegisterer () {
	const handlers = [];

	function add (target, type, handler, opts) {
		target.addEventListener(type, handler, opts);
		handlers.push({
			target: target,
			type: type,
			handler: handler,
			opts: opts
		});
		return this;
	}

	function remove (target, type, handler, opts) {
		// TBD
		return this;
	}

	function removeAll () {
		while (handlers.length) {
			const h = handlers.pop();
			h.target.removeEventListener(h.type, h.handler, h.opts);
		}
	}

	return {
		add: add,
		remove: remove,
		removeAll: removeAll
	};
}

function createClickDispatcher (manual) {
	const PASS_THROUGH = 'passthrough';
	const keys = {};

	let enabled = true;

	function getKey (e) {
		let t = e.target, fragment;
		for (; t; t = t.parentNode) {
			let code = t.nodeName.toLowerCase();
			if (code == 'input') {
				code += '-' + t.type;
			}
			if (/^(?:a|button|input-checkbox|input-radio)$/.test(code)) {
				break;
			}
			if (t.getAttribute && (fragment = t.getAttribute('data-href')) != null) {
				break;
			}
		}
		if (t && fragment == null) {
			fragment = t.getAttribute('href');
			if (fragment == null) {
				fragment = t.getAttribute('data-href');
			}
		}
		return {
			target: t,
			key: fragment
		};
	}

	function dispatch (event, target, key) {
		if (!target || key == null) {
			return;
		}

		// fragment
		if (/^#.+$/.test(key) && key in keys) {
			invoke(event, target, key);
			return;
		}

		// class
		for (let k in keys) {
			if (k.charAt(0) == '.' && target.classList.contains(k.substring(1))) {
				invoke(event, target, k);
				return;
			}
		}

		// no class
		if ('*noclass*' in keys) {
			keys['*noclass*'](event, target);
		}
	}

	function invoke (event, target, key) {
		let result;

		if (enabled) {
			try {
				result = keys[key](event, target);
			}
			catch (err) {
				console.error(`${APP_NAME}: exception in clickDispatcher: ${err.stack}`);
				result = undefined;
			}
		}

		let isAnchor = false;
		for (let elm = event.target; elm; elm = elm.parentNode) {
			if (elm.nodeName.toLowerCase() == 'a') {
				isAnchor = true;
				break;
			}
		}

		if (isAnchor && result !== PASS_THROUGH) {
			event.preventDefault && event.preventDefault();
			event.stopPropagation && event.stopPropagation();
		}
	}

	function add (key, handler) {
		if (Array.isArray(key)) {
			for (const k of key) {
				keys[k] = handler;
			}
		}
		else {
			keys[key] = handler;
		}
		return this;
	}

	function remove (key) {
		if (Array.isArray(key)) {
			for (const k of key) {
				delete keys[k];
			}
		}
		else {
			delete keys[key];
		}
		return this;
	}

	function removeAll () {
		Object.keys(keys).forEach(key => {
			delete keys[key];
		});
	}

	function init () {
		!manual && document.body.addEventListener('click', e => {
			const {target, key} = getKey(e);
			dispatch(e, target, key);
		});
	}

	if (document.body) {
		init();
	}
	else {
		document.addEventListener('DOMContentLoaded', function handler (e) {
			document.removeEventListener(e.type, handler);
			init();
		});
	}

	return {
		add: add,
		remove: remove,
		removeAll: removeAll,
		getKey: getKey,
		dispatch: dispatch,
		get enabled () {return enabled},
		set enabled (v) {enabled = !!v},
		PASS_THROUGH: PASS_THROUGH
	};
}

function createHoverWrapper (element, nodeName, hoverCallback, leaveCallback) {
	let lastHoverElement = null;

	function findTarget (e) {
		while (e) {
			if (e.nodeName.toLowerCase() == nodeName) return e;
			e = e.parentNode;
		}
		return null;
	}

	function mover (e) {
		let fromElement = findTarget(e.relatedTarget);
		let toElement = findTarget(e.target);
		let needInvokeHoverEvent = false;
		let needInvokeLeaveEvent = false;

		if (fromElement != toElement) {
			// causes leave event?
			if (fromElement) {
				if (lastHoverElement != null) {
					needInvokeLeaveEvent = true;
				}
			}

			// causes hover event?
			if (toElement) {
				if (lastHoverElement != toElement) {
					needInvokeHoverEvent = true;
				}
			}

			// causes leave event?
			else {
				if (lastHoverElement != null) {
					needInvokeLeaveEvent = true;
				}
			}
		}

		if (needInvokeLeaveEvent) {
			leaveCallback && leaveCallback({target: lastHoverElement});
			lastHoverElement = null;
		}
		if (needInvokeHoverEvent) {
			hoverCallback && hoverCallback({target: toElement});
			lastHoverElement = toElement;
		}
	}

	function mout (e) {
		let toElement = findTarget(e.relatedTarget);
		if (!toElement && lastHoverElement) {
			leaveCallback && leaveCallback({target: lastHoverElement});
			lastHoverElement = null;
		}
	};

	function dispose () {
		hoverCallback && element.removeEventListener('mouseover', mover);
		leaveCallback && element.removeEventListener('mouseout', mout);
	}

	element = $(element);
	if (!element) {
		throw new Error('createHoverWrapper: element not specified');
	}

	nodeName = nodeName.toLowerCase();
	hoverCallback && element.addEventListener('mouseover', mover);
	leaveCallback && element.addEventListener('mouseout', mout);

	return {
		dispose: dispose
	};
}

/*
 * <<<1 main class of momocan
 *
 * @author akahuku@gmail.com
 */

function createMomocan (opts) {
	const CONTAINER_ID = 'momocan-container';
	const GLOBAL_PERSIST_KEY = 'momocan_globals';
	const SESSION_PERSIST_KEY = 'data-momocan-sessions';
	const EXPORT_PERSIST_KEY = 'momocan_exported';
	const ZOOM_MARGIN = 8;
	const UNDO_MAX = 16;
	const PEN_SIZE_MAX = 24;
	const HIGH_RESOLUTION_FACTOR = 2;
	const LONGPRESS_THRESHOLD_MSECS = 500;
	const LAYER_OUTLINE = '1px dotted #fff';
	const DUMP_UNDO_BUFFER = false;
	const DEFAULT_GLOBALS = {
		palettes: [
			'#800000', '#aa5a56', '#cf9c97', '#e9c2ba', '#f0e0d6',
			'#ffffee', '#eeaa88', '#789922', '#ffffff', '#000000'
		],
		penSize: 3,
		eraserSize: 20,
		enablePenAdjustment: true,
		interpolateScale: 0.3,
		coordUpscaleFactor: 3.4,
		emulatePointedEnd: true,
		blurredEraser: true,
		usePixelatedLines: false,
		useCrossCursor: true,
		closedColorThreshold: 0.2,
		overfillSize: 1,
		sampleMerged: true,
		useHighPrecisionPenSize: false
	};
	const DEFAULT_SESSIONS = {
		canvasInitialized: false,
		canvasWidth: 344,
		canvasHeight: 135,
		foregroundColor: '#800000',
		backgroundColor: '#f0e0d6',
		zoomFactor: 2,
		layerIndex: 2
	};

	let container;

	let globals;
	let sessions;
	let penMode;
	let zoomRatio;
	let contextProfile;
	let context;
	let canvasRect;
	let drawFunction;
	let layerHoverResetTimer;

	let busy = 0;
	let touchEnabled = false;
	let overlaid = false;
	let undoPosition = -1;
	let canvases = {};
	let angle = 0;

	const event = createEventRegisterer();
	const clickDispatcher = createClickDispatcher();
	const longPressDispatcher = createClickDispatcher(true);
	const pointerEventNames = getPointerEventNames();
	const repeatMap = {};
	const points = [];
	const undoBuffer = [];
	const hoverWrappers = [];
	const pointerInfo = {
		clientX: 0,
		clientY: 0,
		buttons: 0,
		captureId: null,
		drag: {
			kind: '',
			state: -1,
			baseLeft: 0,
			baseTop: 0,
			baseAngle: 0,
			startX: 0,
			startY: 0,
			startAngle: 0,
			outRanged: false
		},
		longPress: {
			timer: null
		}
	};
	const paletteClickInfo = {
		index: -1,
		click: null
	};
	const penPositionInfo = {
		computedX: 0,
		computedY: 0,
		lastX: 0,
		lastY: 0,
		lastUsedX: -1,
		lastUsedY: -1
	};
	const penWheelInfo = {
		delta: null,
		time: 0
	};
	const elasticInfo = {
		active: false,
		context: '',
		head: null,
		underImage: null,
		activate: function () {
			this.active = true;
			this.context = contextProfile;
			this.head = points[points.length - 1];
			this.underImage = mergeLayers(0, sessions.layerIndex);
			container.addEventListener(pointerEventNames.move, handleElasticPointerMove);
			updateStatus();
		},
		deactivate: function () {
			points[0] = this.head;
			this.context = '';
			this.head = null;
			this.underImage = null;
			this.active = false;
			container.removeEventListener(pointerEventNames.move, handleElasticPointerMove);
			updateStatus();
		}
	};
	const drawFunctions = {
		/*
		 * normal pen
		 */

		draw_pen_layer: {
			down: (x, y) => {
				ensurePenContext();
				const penOffset = (globals.penSize % 2) / 2.0;
				x += penOffset;
				y += penOffset;
				context.beginPath();
				context.arc(x, y, context.lineWidth / 2, 0, 2 * Math.PI);
				context.closePath();
				context.fill();

				context.beginPath();
				context.moveTo(x, y);
			},
			move: (x, y) => {
				const penOffset = globals.enablePenAdjustment && globals.interpolateScale > 0 ? 0 : (globals.penSize % 2) / 2.0;
				x += penOffset;
				y += penOffset;
				context.lineTo(x, y);
				context.stroke();
				context.beginPath();
				context.moveTo(x, y);
			},
			up: (x, y) => {
				const penOffset = (globals.penSize % 2) / 2.0;
				x += penOffset;
				y += penOffset;
				context.lineTo(x, y);
				context.stroke();
				applyPoints();
			},
			elastic: (x, y) => {
				const penOffset = (globals.penSize % 2) / 2.0;
				x += penOffset;
				y += penOffset;
				const ctx = canvases.layerPen.getContext('2d');
				ctx.save();
				initContext(ctx);
				ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				ctx.beginPath();
				ctx.moveTo(elasticInfo.head[0] + penOffset, elasticInfo.head[1] + penOffset);
				ctx.lineTo(x, y);
				ctx.stroke();
				ctx.restore();
			}
		},
		draw_pen_background: {
			down: (x, y) => drawFunctions.draw_pen_layer.down(x, y),
			move: (x, y) => drawFunctions.draw_pen_layer.move(x, y),
			up: (x, y) => drawFunctions.draw_pen_layer.up(x, y),
			elastic: (x, y) => drawFunctions.draw_pixel_layer.elastic(x, y)
		},

		draw_pixel_layer: {
			down: (x, y) => {
				ensurePenContext();
				line(context, x, y, x, y, globals.penSize);
				penPositionInfo.lastUsedX = x;
				penPositionInfo.lastUsedY = y;
			},
			move: (x, y) => {
				line(context, penPositionInfo.lastUsedX, penPositionInfo.lastUsedY, x, y, globals.penSize);
				penPositionInfo.lastUsedX = x;
				penPositionInfo.lastUsedY = y;
			},
			up: (x, y) => {
				line(context, penPositionInfo.lastUsedX, penPositionInfo.lastUsedY, x, y, globals.penSize);
				applyPoints();
			},
			elastic: (x, y) => {
				const ctx = canvases.layerPen.getContext('2d');
				ctx.save();
				initContext(ctx);
				ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				line(ctx, elasticInfo.head[0], elasticInfo.head[1], x, y, globals.penSize);
				ctx.restore();
			}
		},
		draw_pixel_background: {
			down: (x, y) => drawFunctions.draw_pixel_layer.down(x, y),
			move: (x, y) => drawFunctions.draw_pixel_layer.move(x, y),
			up: (x, y) => drawFunctions.draw_pixel_layer.up(x, y),
			elastic: (x, y) => drawFunctions.draw_pixel_layer.elastic(x, y)
		},

		/*
		 * eraser
		 */

		erase_pen_layer: {
			down: (x, y) => {
				ensureLayerContext();
				const {offsetX, offsetY} = getLayerOffset(context.canvas);
				x -= offsetX;
				y -= offsetY;
				context.save();
				if (/_layer$/.test(contextProfile)) {
					context.globalCompositeOperation = 'destination-out';
				}
				else {
					context.strokeStyle = context.fillStyle = sessions.backgroundColor;
				}
				context.lineWidth = globals.eraserSize;
				context.filter = globals.blurredEraser ? 'blur(1px)' : '';
				context.beginPath();
				context.arc(x, y, context.lineWidth / 2, 0, 2 * Math.PI);
				context.closePath();
				context.fill();

				context.beginPath();
				context.moveTo(x, y);
			},
			move: (x, y) => {
				const {offsetX, offsetY} = getLayerOffset(context.canvas);
				x -= offsetX;
				y -= offsetY;
				context.lineTo(x, y);
				context.stroke();
				context.beginPath();
				context.moveTo(x, y);
			},
			up: (x, y) => {
				const {offsetX, offsetY} = getLayerOffset(context.canvas);
				x -= offsetX;
				y -= offsetY;
				context.lineTo(x, y);
				context.stroke();
				context.restore();
			},
			elastic: (x, y) => {
				const ctx = canvases.layerPen.getContext('2d');
				ctx.save();
				initContext(ctx);
				ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				ctx.drawImage(elasticInfo.underImage, 0, 0);
				ctx.lineWidth = globals.eraserSize;
				ctx.filter = globals.blurredEraser ? 'blur(1px)' : '';
				ctx.strokeStyle = '#ffffff';
				ctx.globalCompositeOperation = 'destination-in';
				ctx.beginPath();
				ctx.moveTo(elasticInfo.head[0], elasticInfo.head[1]);
				ctx.lineTo(x, y);
				ctx.stroke();
				ctx.restore();
			}
		},
		erase_pen_background: {
			down: (x, y) => drawFunctions.erase_pen_layer.down(x, y),
			move: (x, y) => drawFunctions.erase_pen_layer.move(x, y),
			up: (x, y) => drawFunctions.erase_pen_layer.up(x, y),
			elastic: (x, y) => {
				const ctx = canvases.layerPen.getContext('2d');
				ctx.save();
				initContext(ctx);
				ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				ctx.lineWidth = globals.eraserSize;
				ctx.filter = globals.blurredEraser ? 'blur(1px)' : '';
				ctx.strokeStyle = sessions.backgroundColor;
				ctx.beginPath();
				ctx.moveTo(elasticInfo.head[0], elasticInfo.head[1]);
				ctx.lineTo(x, y);
				ctx.stroke();
				ctx.restore();
			}
		},

		erase_pixel_layer: {
			down: (x, y) => {
				ensureLayerContext();
				context.save();
				if (/_layer$/.test(contextProfile)) {
					context.globalCompositeOperation = 'destination-out';
				}
				line(
					context,
					x, y, x, y,
					{size: globals.eraserSize, penCanvas: canvases.crispEraser});
				penPositionInfo.lastUsedX = x;
				penPositionInfo.lastUsedY = y;
			},
			move: (x, y) => {
				line(
					context,
					penPositionInfo.lastUsedX, penPositionInfo.lastUsedY, x, y,
					{size: globals.eraserSize, penCanvas: canvases.crispEraser});
				penPositionInfo.lastUsedX = x;
				penPositionInfo.lastUsedY = y;
			},
			up: (x, y) => {
				line(
					context,
					penPositionInfo.lastUsedX, penPositionInfo.lastUsedY, x, y,
					{size: globals.eraserSize, penCanvas: canvases.crispEraser});
				context.restore();
			},
			elastic: (x, y) => {
				const ctx = canvases.layerPen.getContext('2d');
				ctx.save();
				initContext(ctx);
				ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				line(
					ctx,
					elasticInfo.head[0], elasticInfo.head[1], x, y,
					{size: globals.eraserSize, penCanvas: canvases.crispEraser});
				ctx.globalCompositeOperation = 'source-in';
				ctx.drawImage(elasticInfo.underImage, 0, 0);
				ctx.restore();
			}
		},
		erase_pixel_background: {
			down: (x, y) => drawFunctions.erase_pixel_layer.down(x, y),
			move: (x, y) => drawFunctions.erase_pixel_layer.move(x, y),
			up: (x, y) => drawFunctions.erase_pixel_layer.up(x, y),
			elastic: (x, y) => {
				const ctx = canvases.layerPen.getContext('2d');
				ctx.save();
				initContext(ctx);
				ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				line(
					ctx,
					elasticInfo.head[0], elasticInfo.head[1], x, y,
					{size: globals.eraserSize, penCanvas: canvases.crispEraser});
				ctx.restore();
			}
		},

		/*
		 * flood bucket
		 */

		flood: {
			down: (x, y) => {},
			move: (x, y) => {},
			elastic: (x, y) => {},
			up: (x, y) => {floodColor(x, y, sessions.foregroundColor)}
		},

		/*
		 * select
		 */

		select: {
			down: (x, y) => {},
			move: (x, y) => {},
			elastic: (x, y) => {},
			up: (x, y) => {}
		},

		/*
		 * lasso
		 */

		lasso: {
			down: (x, y) => {},
			move: (x, y) => {},
			elastic: (x, y) => {},
			up: (x, y) => {}
		},

		/*
		 * move
		 */

		move: {
			down: (x, y, e) => {
				if (pointerInfo.drag.kind == '') {
					ensureLayerContext();
					Object.assign(pointerInfo.drag, {
						kind: 'move',
						state: 1,
						baseLeft: getLayerOffsetX(context.canvas),
						baseTop: getLayerOffsetY(context.canvas),
						startX: x,
						startY: y
					});
				}
			},
			move: (x, y) => {
				if (pointerInfo.drag.kind != 'move') return;
				if (sessions.layerIndex == 0) return;

				const maxWidth = window.Akahuku.storage.config.tegaki_max_width.max;
				const maxHeight = window.Akahuku.storage.config.tegaki_max_height.max;

				const left = Math.floor(minmax(
					sessions.canvasWidth / 2 - maxWidth / 2 - maxWidth,
					pointerInfo.drag.baseLeft + (x - pointerInfo.drag.startX),
					sessions.canvasWidth / 2 + maxWidth / 2));
				context.canvas.style.left = left + 'px';
				setLayerOffsetX(context.canvas, left);

				const top = Math.floor(minmax(
					sessions.canvasHeight / 2 - maxHeight / 2 - maxHeight,
					pointerInfo.drag.baseTop + (y - pointerInfo.drag.startY),
					sessions.canvasHeight / 2 + maxHeight / 2));
				context.canvas.style.top = top + 'px';
				setLayerOffsetY(context.canvas, top);
			},
			elastic: (x, y) => {},
			up: (x, y) => {
				if (pointerInfo.drag.kind == 'move') {
					//const {offsetX, offsetY} = getLayerOffset(context.canvas);
					//conlog(`offset updated to ${offsetX}, ${offsetY}`);
				}
			}
		},

		/*
		 * colorpicker
		 */

		colorpicker: {
			down: (x, y) => {},
			move: (x, y) => {},
			elastic: (x, y) => {},
			up: (x, y) => {
				if (x < 0 || x >= sessions.canvasWidth || y < 0 || y >= sessions.canvasHeight) return;
				const imageData = mergeLayers().getContext('2d').getImageData(x, y, 1, 1);
				const palette = imageData.data[0] << 16 | imageData.data[1] << 8 | imageData.data[2];
				sessions.foregroundColor = setColor($qs('.current-color [href="#fg-color"]', container), palette);
				updateCursor();
			}
		}
	};

	const drawTools = {
		flip_horizontally: () => {
			filterBase((ctx, tmp, index) => {
				ctx.scale(-1, 1);
				ctx.drawImage(tmp, -ctx.canvas.width, 0, ctx.canvas.width, ctx.canvas.height);
				if (index > 0) {
					let offset = getLayerOffsetX(ctx.canvas);
					let flippedOffset = sessions.canvasWidth - (offset + ctx.canvas.width);
					ctx.canvas.style.left = flippedOffset + 'px';
					setLayerOffsetX(ctx.canvas, flippedOffset);
				}
			});
		},
		flip_vertically: () => {
			filterBase((ctx, tmp, index) => {
				ctx.scale(1, -1);
				ctx.drawImage(tmp, 0, -ctx.canvas.height, ctx.canvas.width, ctx.canvas.height);
				if (index > 0) {
					let offset = getLayerOffsetY(ctx.canvas);
					let flippedOffset = sessions.canvasHeight - (offset + ctx.canvas.height);
					ctx.canvas.style.top = flippedOffset + 'px';
					setLayerOffsetY(ctx.canvas, flippedOffset);
				}
			});
		},
		rotate_left: () => {
			return rotateImage(false)
		},
		rotate_right: () => {
			return rotateImage(true)
		},
		merge_layers: () => {
			const baseCanvas = canvases.layer0;
			const ctx = baseCanvas.getContext('2d');
			for (let i = 1; i < 3; i++) {
				const canvas = canvases[`layer${i}`];
				const {offsetX, offsetY} = getLayerOffset(canvas);
				ctx.drawImage(canvas, offsetX, offsetY);
				canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
			}
		},
		merge_layers_longpress: target => {
			return startPopupMenu(target, [
				{
					href: '#merge_layers',
					text: 'Merge layers to background'
				},
				{
					href: '#merge_down',
					text: 'Merge current layer to layer below'
				}
			]).then(anchor => {
				if (!anchor) {
					return;
				}

				switch (anchor.getAttribute('href')) {
				case '#merge_layers':
					drawTools.merge_layers();
					break;
				case '#merge_down':
					if (sessions.layerIndex > 0) {
						const src = canvases[`layer${sessions.layerIndex}`];
						const dst = canvases[`layer${sessions.layerIndex - 1}`];
						const {offsetX: srcOffsetX, offsetY: srcOffsetY} = getLayerOffset(src);
						const {offsetX: dstOffsetX, offsetY: dstOffsetY} = getLayerOffset(dst);
						dst.getContext('2d').drawImage(
							src,
							srcOffsetX - dstOffsetX,
							srcOffsetY - dstOffsetY);
						src.getContext('2d').clearRect(
							0, 0, src.width, src.height);
					}
					break;
				}
			});
		},
		init_canvas: () => {
			$qs('a[href*="#reset"]', container).click();
			setCanvasSize(DEFAULT_SESSIONS.canvasWidth, DEFAULT_SESSIONS.canvasHeight, true);
			return setZoomFactor(sessions.zoomFactor);
		},
		init_canvas_longpress: target => {
			return startPopupMenu(target, [
				{
					href: '#init-canvas',
					text: 'Initialize canvas size and contents'
				},
				{
					href: '#clear-all-layers',
					text: 'Clear all layers'
				},
				{
					href: '#clear-layer',
					text: 'Clear current layer'
				}
			]).then(anchor => {
				if (!anchor) {
					return;
				}

				switch (anchor.getAttribute('href')) {
				case '#init-canvas':
					drawTools.init_canvas();
					break;
				case '#clear-all-layers':
					drawTools.clear_all_layers();
					break;
				case '#clear-layer':
					drawTools.clear_layer();
					break;
				}
			});
		},
		paint_layer: () => {
			filterBase((ctx, tmp, index) => {
				if (index == sessions.layerIndex) {
					ctx.fillStyle = sessions.foregroundColor;
					ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				}
				else {
					ctx.drawImage(tmp, 0, 0);
				}
			});
		},
		clear_all_layers: () => {
			filterBase((ctx, tmp, index) => {
				if (index == 0) {
					ctx.fillStyle = sessions.backgroundColor;
					ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				}
				else {
					ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				}
			});
		},
		clear_layer: () => {
			filterBase((ctx, tmp, index) => {
				if (index == sessions.layerIndex) {
					if (index == 0) {
						ctx.fillStyle = sessions.backgroundColor;
						ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
					}
				}
				else {
					ctx.drawImage(tmp, 0, 0);
				}
			});
		},
		resize: () => {
			const result = window.prompt(
				'Enter the width and height of the new canvas, separated by a space. Leave empty to use default.',
				`${sessions.canvasWidth} ${sessions.canvasHeight}`);
			if (result === null) return;

			// 0  U+0030 - 9  U+0039
			// ０ U+ff10 - ９ U+ff19
			const re = /(\d+)\s+(\d+)/.exec(result
				.replace(/[\uff10-\uff19]/g, $0 => String.fromCharCode($0.charCodeAt(0) - 0xff10 + 0x0030))
				.replace(/\u3000/g, ' '));
			let width = DEFAULT_SESSIONS.canvasWidth;
			let height = DEFAULT_SESSIONS.canvasHeight;
			if (re) {
				width = minmax(
					window.Akahuku.storage.config.tegaki_max_width.min,
					re[1] - 0,
					window.Akahuku.storage.config.tegaki_max_width.max);
				height = minmax(
					window.Akahuku.storage.config.tegaki_max_height.min,
					re[2] - 0,
					window.Akahuku.storage.config.tegaki_max_height.max);
			}

			return setCanvasSizePreserving(width, height);
		},
		resize_longpress: target => {
			return startPopupMenu(target, [
				{ href: '#input',   text: 'Enter your canvas size...' },
				{ href: '#344-135', text: '344 x 135' },
				{ href: '#135-344', text: '135 x 344' },
				{ href: '#344-344', text: '344 x 344' },
				{ href: '#400-400', text: '400 x 400' }
			]).then(anchor => {
				if (!anchor) {
					return;
				}

				switch (anchor.getAttribute('href')) {
				case '#input':
					return drawTools.resize();
					break;
				case '#344-135':
				case '#135-344':
				case '#344-344':
				case '#400-400':
					const re = /#(\d+)-(\d+)/.exec(anchor.getAttribute('href'));
					return setCanvasSizePreserving(re[1] - 0, re[2] - 0);
					break;
				}
			});
		}
	};

	const keyboardShortcuts = {
		' ': (e, isRepeated) => {
			// space: scroll modifier
			if (!isRepeated && pointerInfo.drag.kind == '') {
				container.classList.remove('running');
				pointerInfo.drag.kind = 'scroll';
				pointerInfo.drag.state = 0;
				// This event object has no information to init the drag structure,
				// So initialization is done in handlePointerMove().
			}
		},
		'c-z': '#draw-undo',
		'c-y': '#draw-redo',
		'a': () => { setEnablePenAdjustment(!globals.enablePenAdjustment) },
		'b': () => { setEmulatePointedEnd(!globals.emulatePointedEnd) },
		'd': '#reset',
		'x': '#swap',
		'p': () => { $qs('a[href="#draw-method"][data-index="0"]', container).click() },
		'e': () => { $qs('a[href="#draw-method"][data-index="1"]', container).click() },
		'g': () => { $qs('a[href="#draw-method"][data-index="2"]', container).click() },
		'm': () => { $qs('a[href="#draw-method"][data-index="3"]', container).click() },
		'l': () => { $qs('a[href="#draw-method"][data-index="4"]', container).click() },
		'v': () => { $qs('a[href="#draw-method"][data-index="5"]', container).click() },
		'c-0': () => { $qs('input[name="draw-zoom"][value="0"]', container).click() },
		'c-1': () => { $qs('input[name="draw-zoom"][value="1"]', container).click() },
		'c-2': () => { $qs('input[name="draw-zoom"][value="2"]', container).click() },
		'c-3': () => { $qs('input[name="draw-zoom"][value="3"]', container).click() },
		'c-4': () => { $qs('input[name="draw-zoom"][value="4"]', container).click() },
		'{': () => keyboardShortcuts['['](),
		'}': () => keyboardShortcuts[']'](),
		'[': () => {
			switch (penMode) {
			case 0:
				setPenSize(
					{isHighPrecision: true, delta: -1},
					{isHighPrecision: true});
				break;
			case 1:
				setPenSize(
					{isHighPrecision: true},
					{isHighPrecision: true, delta: -1});
				break;
			}
		},
		']': () => {
			switch (penMode) {
			case 0:
				setPenSize(
					{isHighPrecision: true, delta: 1},
					{isHighPrecision: true});
				break;
			case 1:
				setPenSize(
					{isHighPrecision: true},
					{isHighPrecision: true, delta: 1});
				break;
			}
		}
	};

	// <<<2 misc functions

	function restorePersistents () {
		/*
		 * global persistents
		 */

		globals = Object.assign(
			JSON.parse(JSON.stringify(DEFAULT_GLOBALS)),
			parsejson(window.localStorage.getItem(GLOBAL_PERSIST_KEY), null));

		// palettes
		initPalettes(globals.palettes);

		/*
		 * session persistents
		 */

		sessions = Object.assign(
			JSON.parse(JSON.stringify(DEFAULT_SESSIONS)),
			parsejson(container.getAttribute(SESSION_PERSIST_KEY), null));

		// current colors
		setColor($qs('.current-color [href="#fg-color"]', container), sessions.foregroundColor);
		setColor($qs('.current-color [href="#bg-color"]', container), sessions.backgroundColor);
		updateCrispPenColor();
		updateCrispEraserColor();

		// draw method options
		setPenMode(0); // NOTE: initial pen mode is always normal.
		setPenSize(globals.penSize, globals.eraserSize);
		setEnablePenAdjustment(globals.enablePenAdjustment);
		setInterpolateScale(globals.interpolateScale);
		setCoordUpscaleFactor(globals.coordUpscaleFactor);
		setEmulatePointedEnd(globals.emulatePointedEnd);
		setBlurredEraser(globals.blurredEraser);
		setUsePixelatedLines(globals.usePixelatedLines);
		setUseCrossCursor(globals.useCrossCursor);
		setClosedColorThreshold(globals.closedColorThreshold);
		setOverfillSize(globals.overfillSize);
		setSampleMerged(globals.sampleMerged);
		setUseHighPrecisionPenSize(globals.useHighPrecisionPenSize);

		// zoom factor
		//
		// at this point, the size of each element has not
		// been determined, so canvas size can not be
		// computed. see the last transition block of start().
		//setZoomFactor(sessions.zoomFactor);

		// layer
		setLayerIndex(sessions.layerIndex);

		// canvas size
		let exported;
		if (!sessions.canvasInitialized
		&&  (exported = parsejson(window.localStorage.getItem(EXPORT_PERSIST_KEY)))
		&&  'width' in exported && 'height' in exported && 'layers' in exported) {
			window.localStorage.removeItem(EXPORT_PERSIST_KEY);
			return restoreExportedCanvas(exported);
		}
		else {
			setCanvasSize(sessions.canvasWidth, sessions.canvasHeight, !sessions.canvasInitialized);
			return Promise.resolve();
		}
	}

	function initPalettes (palettes) {
		$qsa('.sub-colors a', container).forEach((node, index) => {
			if (!palettes[index]) return;
			setColor(node, palettes[index]);
		});
	}

	function exportCanvas () {
		const result = {
			width: sessions.canvasWidth,
			height: sessions.canvasHeight,
			layers: [],
			layerInfo: []
		};

		for (let i = 0; i < 3; i++) {
			const canvas = canvases[`layer${i}`];
			result.layers.push(canvas.toDataURL());
			result.layerInfo.push({
				offsetX: getLayerOffsetX(canvas),
				offsetY: getLayerOffsetY(canvas)
			});
		}

		window.localStorage.setItem(EXPORT_PERSIST_KEY, JSON.stringify(result));
	}

	function restoreExportedCanvas (data) {
		const canvasWidth = Math.min(data.width, window.Akahuku.storage.config.tegaki_max_width.max);
		const canvasHeight = Math.min(data.height, window.Akahuku.storage.config.tegaki_max_height.max);
		const layerWidth = window.Akahuku.storage.config.tegaki_max_width.max;
		const layerHeight = window.Akahuku.storage.config.tegaki_max_height.max;

		sessions.canvasWidth = canvasWidth;
		sessions.canvasHeight = canvasHeight;

		const wrap = $qs('.canvas-wrap', container);
		wrap.style.width = canvasWidth + 'px';
		wrap.style.height = canvasHeight + 'px';

		canvases.hd.width = canvasWidth * HIGH_RESOLUTION_FACTOR;
		canvases.hd.height = canvasHeight * HIGH_RESOLUTION_FACTOR;

		canvases.layerPen.width = canvasWidth;
		canvases.layerPen.height = canvasHeight;

		enterBusy('restoreExportedCanvas');
		return Promise.all(data.layers.map(layer => loadImage(layer)))
			.then(images => {
				images.forEach((image, index) => {
					const canvas = canvases[`layer${index}`];
					canvas.width = index == 0 ? canvasWidth : layerWidth;
					canvas.height = index == 0 ? canvasHeight : layerHeight;

					const ctx = canvas.getContext('2d');
					ctx.clearRect(0, 0, canvas.width, canvas.height);
					ctx.drawImage(image,
						0, 0,
						image.naturalWidth, image.naturalHeight,
						Math.floor(canvas.width / 2 - image.naturalWidth / 2),
						Math.floor(canvas.height / 2 - image.naturalHeight / 2),
						image.naturalWidth, image.naturalHeight);

					if (index > 0) {
						let layerOffsetX,layerOffsetY;
						if ('layerInfo' in data && data.layerInfo[index]) {
							layerOffsetX = Math.floor(data.layerInfo[index].offsetX);
							layerOffsetY = Math.floor(data.layerInfo[index].offsetY);
						}
						if (isNaN(layerOffsetX) || layerOffsetX < -layerWidth || layerOffsetX > layerWidth) {
							layerOffsetX = Math.floor(canvasWidth / 2 - layerWidth / 2);
						}
						if (isNaN(layerOffsetY) || layerOffsetY < -layerHeight || layerOffsetY > layerHeight) {
							layerOffsetY = Math.floor(canvasHeight / 2 - layerHeight / 2);
						}
						setLayerOffset(canvas, layerOffsetX, layerOffsetY);
					}
				});
			})
			.finally(() => {
				leaveBusy('restoreExportedCanvas');
				context = canvasRect = null;
			});
	}

	function enterBusy (tag) {
		//conlog(`enterBusy: ${tag}`);
		busy++;
	}

	function leaveBusy (tag) {
		//conlog(`leaveBusy: ${tag}`);
		busy--;
		updateStatus();
	}

	function isTextInputElement (elm) {
		switch (elm.nodeName.toLowerCase()) {
		case 'textarea':
			return true;
			break;
		case 'input':
			if (/^(?:text|password)$/i.test(elm.type)) return true;
			break;
		}
		return false;
	}

	function getPointerEventNames () {
		if ('PointerEvent' in window) {
			return {
				down: 'pointerdown',
				move: 'pointermove',
				up:   'pointerup'
			};
		}
		if ('ontouchstart' in window) {
			return {
				down: 'touchstart',
				move: 'touchmove',
				up:   'touchup'
			};
		}
		return {
			down: 'mousedown',
			move: 'mousemove',
			up:   'mouseup'
		};
	}

	function getAdjustedPosition (x, y, deg) {
		x -= canvasRect.canvas.left;
		y -= canvasRect.canvas.top;

		if (deg) {
			x -= canvasRect.canvas.width / 2;
			y -= canvasRect.canvas.height / 2;
			const r = -deg * Math.PI / 180;
			const xx = x * Math.cos(r) - y * Math.sin(r);
			const yy = x * Math.sin(r) + y * Math.cos(r);
			x = xx + sessions.canvasWidth * zoomRatio / 2;
			y = yy + sessions.canvasHeight * zoomRatio / 2;
		}

		x = Math.floor(x / zoomRatio);
		y = Math.floor(y / zoomRatio);

		return {x: x, y: y};
	}

	function getAdjustedCanvasSize (z, a) {
		z || (z = zoomRatio);
		a || (a = angle);

		const rad = a * Math.PI / 180;
		const width = sessions.canvasWidth * z;
		const height = sessions.canvasHeight * z;
		const px = [], py = [];
		[
			[0,     0],
			[0,     height],
			[width, 0],
			[width, height]
		].forEach(p => {
			const [x, y] = p;
			px.push(x * Math.cos(rad) - y * Math.sin(rad));
			py.push(x * Math.sin(rad) + y * Math.cos(rad));
		});
		const adjustedCanvasWidth = Math.ceil(Math.max.apply(Math, px) - Math.min.apply(Math, px));
		const adjustedCanvasHeight = Math.ceil(Math.max.apply(Math, py) - Math.min.apply(Math, py));

		return {
			width: adjustedCanvasWidth,
			height: adjustedCanvasHeight
		};
	}

	function getCanvasPos (e, isLast) {
		ensureCanvasRect();

		let x, y;
		// not tested
		if ('touches' in e && e.touches.length) {
			x = e.touches[0].clientX;
			y = e.touches[0].clientY;
		}
		else {
			x = e.clientX;
			y = e.clientY;
		}

		const adjusted = getAdjustedPosition(x, y, angle);
		x = adjusted.x;
		y = adjusted.y;

		if (points.length == 0) {
			penPositionInfo.computedX = x;
			penPositionInfo.computedY = y;
		}
		else {
			if (!isLast && penPositionInfo.lastX == x && penPositionInfo.lastY == y) {
				return [
					undefined, undefined,
					x, y,
					Date.now(), e.type
				];
			}

			const scale = penMode == 0 && globals.enablePenAdjustment ? 1.0 / globals.coordUpscaleFactor : 1.0;
			penPositionInfo.computedX += (x - penPositionInfo.computedX) * scale;
			penPositionInfo.computedY += (y - penPositionInfo.computedY) * scale;
		}

		penPositionInfo.lastX = x;
		penPositionInfo.lastY = y;

		const result = [
			penPositionInfo.computedX, penPositionInfo.computedY,
			x, y,
			Date.now(), e.type
		];
		points.push(result);
		return result;
	}

	function getLayerOffsetX (canvas) {
		return (canvas.dataset.offsetX || 0) - 0;
	}

	function getLayerOffsetY (canvas) {
		return (canvas.dataset.offsetY || 0) - 0;
	}

	function getLayerOffset (canvas) {
		return {
			offsetX: (canvas.dataset.offsetX || 0) - 0,
			offsetY: (canvas.dataset.offsetY || 0) - 0
		};
	}

	function setLayerOffsetX (canvas, offsetX) {
		canvas.dataset.offsetX = offsetX;
	}

	function setLayerOffsetY (canvas, offsetY) {
		canvas.dataset.offsetY = offsetY;
	}

	function setLayerOffset (canvas, offsetX, offsetY) {
		canvas.dataset.offsetX = offsetX;
		canvas.dataset.offsetY = offsetY;
	}

	function regalizeAngle (a) {
		while (a > 180) a -= 360;
		while (a < -180) a += 360;
		return a;
	}

	function lookupLongPressHandler (e) {
		const {target, key} = longPressDispatcher.getKey(e);
		if (!target || key == null) return false;

		function up (e) {
			container.removeEventListener(e.type, up);
			if (pointerInfo.longPress.timer) {
				clearTimeout(pointerInfo.longPress.timer);
				pointerInfo.longPress.timer = null;
			}
		}

		pointerInfo.longPress.timer = setTimeout((target, key) => {
			pointerInfo.longPress.timer = null;
			container.removeEventListener(pointerEventNames.up, up);
			const elm = document.elementFromPoint(pointerInfo.clientX, pointerInfo.clientY);
			const {target: currentTarget, key: currentKey} = longPressDispatcher.getKey({target: elm});
			if (target == currentTarget) {
				clickDispatcher.enabled = false;
				longPressDispatcher.dispatch({target: target}, target, key);
			}
		}, LONGPRESS_THRESHOLD_MSECS, target, key);

		container.addEventListener(pointerEventNames.up, up);
		return true;
	}

	function updateStatus (s) {
		s || (s = {});

		const w = 'canvasWidth' in s ? s.canvasWidth : sessions.canvasWidth;
		if (w == undefined) return;

		const h = 'canvasHeight' in s ? s.canvasHeight : sessions.canvasHeight;
		if (h == undefined) return;

		const z = 'zoomRatio' in s ? s.zoomRatio : zoomRatio;
		if (z == undefined) return;

		const a = 'angle' in s ? s.angle : angle;
		if (a == undefined) return;

		const elastic = elasticInfo.active ? 'Straight line' : '';
		const drawMethodName = $qs('.draw-method-text', container).textContent;

		let drawMethodOptions = [];
		if (penMode == 0 && globals.enablePenAdjustment) drawMethodOptions.push('correction');
		if (penMode == 0 && globals.emulatePointedEnd) drawMethodOptions.push('tail');
		drawMethodOptions = drawMethodOptions.length ? ` (${drawMethodOptions.join(', ')})` : '';

		const t = `${w} x ${h} (${z.toFixed(1)}x, ${a}°) - Tool: ${elastic}${drawMethodName}${drawMethodOptions}`;
		const status = $('momocan-status');
		status.textContent != t && $t(status, t);
	}

	function updateHUD (s) {
		let hud = $qs('.canvas-container .hud', container);
		let parent;
		if (hud) {
			parent = hud.parentElement;
		}
		else {
			const wrap = $qs('.canvas-wrap', container);
			parent = wrap.appendChild(document.createElement('div'));
			parent.classList.add('hud-wrap');
			hud = parent.appendChild(document.createElement('div'));
			hud.classList.add('hud');
		}

		ensureCanvasRect();
		parent.style.width = canvasRect.canvas.width + 'px';
		parent.style.height = canvasRect.canvas.height + 'px';
		$t(hud, s);
	}

	function releaseHUD () {
		const hud = $qs('.canvas-container .hud', container);
		if (hud) {
			const parent = hud.parentElement;
			parent.parentElement.removeChild(parent);
		}
	}

	// <<<2 setters

	function setCanvasSize (width, height, initContent) {
		sessions.canvasWidth = width;
		sessions.canvasHeight = height;

		const wrap = $qs('.canvas-wrap', container);
		wrap.style.width = width + 'px';
		wrap.style.height = height + 'px';

		canvases.hd.width = width * HIGH_RESOLUTION_FACTOR;
		canvases.hd.height = height * HIGH_RESOLUTION_FACTOR;

		canvases.layerPen.width = width;
		canvases.layerPen.height = height;

		if (initContent) {
			for (let i = 0; i < 3; i++) {
				const canvas = canvases[`layer${i}`];
				if (i == 0) {
					canvas.width = width;
					canvas.height = height;
					const ctx = canvas.getContext('2d');
					ctx.fillStyle = sessions.backgroundColor;
					ctx.fillRect(0, 0, canvas.width, canvas.height);
				}
				else {
					canvas.width = window.Akahuku.storage.config.tegaki_max_width.max;
					canvas.height = window.Akahuku.storage.config.tegaki_max_height.max;
					const ctx = canvas.getContext('2d');
					ctx.clearRect(0, 0, canvas.width, canvas.height);

					const layerOffsetX = Math.floor(width / 2 - canvas.width / 2);
					const layerOffsetY = Math.floor(height / 2 - canvas.height / 2);
					setLayerOffset(canvas, layerOffsetX, layerOffsetY);
				}
			}
		}

		context = canvasRect = null;
	}

	function setCanvasSizePreserving (width, height) {
		// store original layer images
		const originals = [];
		for (let i = 0; i < 1; i++) {
			const srcCanvas = canvases[`layer${i}`];
			const dstCanvas = document.createElement('canvas');
			dstCanvas.width = srcCanvas.width;
			dstCanvas.height = srcCanvas.height;
			dstCanvas.getContext('2d').drawImage(srcCanvas, 0, 0);

			originals.push(dstCanvas);
		}

		// change canvas size
		container.classList.remove('running');
		setCanvasSize(width, height);

		// restore original images
		for (let i = 0; i < 3; i++) {
			const canvas = canvases[`layer${i}`];
			if (i == 0) {
				canvas.width = width;
				canvas.height = height;

				const ctx = canvas.getContext('2d');
				ctx.fillStyle = sessions.backgroundColor;
				ctx.fillRect(0, 0, width, height);

				ctx.drawImage(
					originals[0],
					Math.floor(width / 2 - originals[0].width / 2),
					Math.floor(height / 2 - originals[0].height / 2));
			}
			else {
				let {offsetX, offsetY} = getLayerOffset(canvas);

				offsetX = Math.floor(offsetX + (width / 2 - originals[0].width / 2));
				offsetY = Math.floor(offsetY + (height / 2 - originals[0].height / 2));

				canvas.style.left = offsetX + 'px';
				canvas.style.top = offsetY + 'px';
				setLayerOffset(canvas, offsetX, offsetY);
			}
		}

		// termination
		context = canvasRect = null;
		return setZoomFactor(sessions.zoomFactor)
			.then(() => delay(100))
			.then(() => {
				container.classList.add('running');
			});
	}

	function setColor (node, color) {
		let hex;

		if (/^#[0-9a-f]{6}$/i.test(color)) {
			hex = color;
		}
		else if (/$#([0-9a-f])([0-9a-f])([0-9a-f])$/i.test(color)) {
			hex = '#' +
				RegExp.$1 + RegExp.$1 +
				RegExp.$2 + RegExp.$2 +
				RegExp.$3 + RegExp.$3;
		}
		else {
			const rgb = [
				(color >> 16) & 0xff,
				(color >> 8) & 0xff,
				color & 0xff
			];
			hex = '#' + rgb
				.map(v => ('00' + v.toString(16)).substr(-2))
				.join('');
		}

		if (node) {
			if (node.hasAttribute('data-index')) {
				node.title = `${hex} (Press and hold to change)`;
			}
			else {
				node.title = hex;
			}
			node.style.background = hex;
			node.setAttribute('data-palette', hex);
		}

		return hex;
	}

	function setPenMode (mode) {
		penMode = mode = minmax(0, mode, 5);

		const methodText = $qs('.draw-method-text', container);
		const subText = $qs('.draw-method-text + .draw-subtext', container);

		const methodElement = $qs(`.draw-method-list-wrap [data-index="${mode}"]`, container);
		const methodKey = methodElement.getAttribute('data-key');
		const methodName = methodElement.getAttribute('title').replace(/\s+\([^)]*\)$/, '');

		// update method name
		$t(methodText, methodName);

		// show corresponding option panel
		$qsa('.draw-method-options[data-target-method]', container).forEach(node => {
			node.classList.add('hide');
		});
		$qsa(`.draw-method-options[data-target-method="${methodKey}"]`, container).forEach(node => {
			node.classList.remove('hide');
		});

		// other method specific stuff...
		switch (penMode) {
		case 0:
		case 1:
			subText.classList.remove('hide');
			break;
		case 2:
		case 5:
			subText.classList.add('hide');
			break;

		case 3:
		case 4:
			subText.classList.add('hide');
			alert('Not yet available');
			break;
		}

		$qsa('.draw-method-list-wrap a', container).forEach(node => {
			node.classList.remove('active');
		});
		methodElement.classList.add('active');
		updateStatus();

		context = null;
	}

	function setEnablePenAdjustment (value) {
		globals.enablePenAdjustment = !!value;

		$qsa('.enable-pen-adjustment', container).forEach(node => {
			node.checked = !!value;
		});

		updateStatus();
	}

	function setInterpolateScale (scale) {
		globals.interpolateScale = scale = minmax(0, scale, 1);

		$qsa('.interpolate-scale', container).forEach(node => {
			node.value = scale;
		});

		$qsa('.interpolate-text', container).forEach(node => {
			$t(node, scale.toFixed(2));
		});

		context = null;
	}

	function setPenSize (psize, esize, allowFloat) {
		if (typeof psize == 'object') {
			let value = globals.penSize;

			if ('value' in psize) {
				value = psize.value;
			}
			if ('isHighPrecision' in psize && psize.isHighPrecision) {
				value = Math.floor(value * 3 + (psize.delta || 0)) / 3;
				value = minmax(1 / 3, value, PEN_SIZE_MAX);
			}
			else {
				value = value + (psize.delta || 0);
				value = minmax(1, Math.floor(value), PEN_SIZE_MAX);
			}
			psize = value;
		}
		else {
			if (psize == undefined) {
				psize = globals.penSize;
			}
			if (allowFloat) {
				psize = minmax(1 / 3, psize, PEN_SIZE_MAX);
			}
			else {
				psize = minmax(1, Math.floor(psize), PEN_SIZE_MAX);
			}
		}

		if (typeof esize == 'object') {
			let value = globals.eraserSize;

			if ('value' in esize) {
				value = esize.value;
			}
			if ('isHighPrecision' in esize && esize.isHighPrecision) {
				value = Math.floor(value * 3 + (esize.delta || 0)) / 3;
				value = minmax(1 / 3, value, PEN_SIZE_MAX);
			}
			else {
				value = value + (esize.delta || 0);
				value = minmax(1, Math.floor(value), PEN_SIZE_MAX);
			}
			esize = value;
		}
		else {
			if (esize == undefined) {
				esize = globals.eraserSize;
			}
			if (allowFloat) {
				esize = minmax(1 / 3, esize, PEN_SIZE_MAX);
			}
			else {
				esize = minmax(1, Math.floor(esize), PEN_SIZE_MAX);
			}
		}

		let size;
		if (penMode == 0) {
			size = psize;
		}
		else if (penMode == 1) {
			size = esize;
		}

		if (size) {
			globals.penSize = psize;
			globals.eraserSize = esize;

			$qsa('.draw-method-text + .draw-subtext', container).forEach(node => {
				$t(node, `${size.toFixed(1)}px`);
			});

			[
				{className: 'pen', size: psize},
				{className: 'eraser', size: esize}
			].forEach(obj => {
				// size indicator
				const canvas = $qs(`.${obj.className}-size-canvas`, container);
				const ctx = canvas.getContext('2d');
				const w = canvas.width;
				const h = canvas.height;
				ctx.clearRect(0, 0, w, h);
				ctx.beginPath();
				ctx.arc(w / 2, h / 2, obj.size / 2, 0, 2 * Math.PI);
				ctx.closePath();
				ctx.fillStyle = DEFAULT_SESSIONS.foregroundColor;
				ctx.fill();

				// size range
				$qsa(`.${obj.className}-size-range`, container).forEach(node => {
					node.value = size;
				});
			});
		}

		updateCursor(size);
		context = null;
	}

	function setCoordUpscaleFactor (factor) {
		globals.coordUpscaleFactor = factor = minmax(1, factor, 4);

		$qsa('.coord-upscale-factor', container).forEach(node => {
			node.value = factor;
		});

		$qsa('.coord-upscale-text', container).forEach(node => {
			$t(node, factor.toFixed(2));
		});
	}

	function setEmulatePointedEnd (value) {
		globals.emulatePointedEnd = !!value;

		$qsa('.emulate-pointed-end', container).forEach(node => {
			node.checked = !!value;
		});

		updateStatus();
	}

	function setBlurredEraser (value) {
		globals.blurredEraser = !!value;

		$qsa('.blurred-eraser', container).forEach(node => {
			node.checked = !!value;
		});
	}

	function setUsePixelatedLines (value) {
		globals.usePixelatedLines = !!value;

		$qsa('.use-pixelated-lines', container).forEach(node => {
			node.checked = !!value;
		});
	}

	function setUseCrossCursor (value) {
		globals.useCrossCursor = !!value;

		$qsa('.use-cross-cursor', container).forEach(node => {
			node.checked = !!value;
		});

		updateCursor();
	}

	function setClosedColorThreshold (value) {
		globals.closedColorThreshold = value = minmax(0, value, 0.5);

		$qsa('.closed-color-threshold-range', container).forEach(node => {
			node.value = value;
		});

		$qsa('.closed-color-threshold-text', container).forEach(node => {
			$t(node, value.toFixed(2));
		});
	}

	function setOverfillSize (value) {
		globals.overfillSize = value = minmax(0, value, 10);

		$qsa('.overfill-size-range', container).forEach(node => {
			node.value = value;
		});

		$qsa('.overfill-size-text', container).forEach(node => {
			$t(node, value.toFixed(1));
		});
	}

	function setSampleMerged (value) {
		globals.sampleMerged = !!value;

		$qsa('.sample-merged', container).forEach(node => {
			node.checked = !!value;
		});
	}

	function setUseHighPrecisionPenSize (value) {
		globals.useHighPrecisionPenSize = !!value;

		$qsa('.use-high-precision-pen-size', container).forEach(node => {
			node.checked = !!value;
		});
	}

	function setZoomFactor (value, isRatio) {
		if (isRatio) {
			zoomRatio = minmax(1, value, 4);
			sessions.zoomFactor = Math.floor(zoomRatio);
		}
		else {
			zoomRatio = minmax(1, value, 4);
			sessions.zoomFactor = minmax(0, value, 4);
		}

		const screenRect = getBoundingClientRect(container);

		const toolboxContainer = $qs('.toolbox-container', container);
		const toolboxInnerRect = getBoundingClientRect($qs('.toolbox-container-inner', container));

		const canvasContainer = $qs('.canvas-container', container);
		const canvasWrap = $qs('.canvas-wrap', container);
		const canvasWrapInner = $qs('.canvas-wrap-inner', container);

		const footerContainer = $qs('.footer-container', container);
		const footerInnerRect = getBoundingClientRect($qs('.footer-container-inner', container));

		// compute asset sizes
		const canvasContainerWidthMax = screenRect.width;
		const canvasContainerHeightMax = screenRect.height - (ZOOM_MARGIN * 2 + toolboxInnerRect.height + footerInnerRect.height);

		/*
		 *  +-toolboxContainer---------------------------------------------------------------------------------+
		 *  |                                                                                                  |
		 *  |                                                                                                  |
		 *  |                                                                                                  |
		 *  |                                                                                                  |
		 *  |                                                                                                  |
		 *  +--------------------------------------------------------------------------------------------------+
		 *  +-canvasContainer----------------------------------------------------------------------------------+
		 *  |                            +-canvasWrap-------------------------+                                |
		 *  |                            |       /~~--_                       |                                |
		 *  |                            |      /      ~~--_                  |                                |
		 *  |                            |     /            ~~--_             |                                |
		 *  |                            |    /         canvasWidth--_        |                                |
		 *  |                            |   /                        ~~--_   |                                |
		 *  |                            |  /                              ~~-|                                |
		 *  |                            | /                                 /|                                |
		 *  |                            |/_                                / |                                |
		 *  |                            |  ~~--_                          /  |                                |
		 *  |                            |       ~~--_                    /   |                                |
		 *  |                            |            ~~--_              /    |                                |
		 *  |                            |                 ~~--_        /     |                                |
		 *  |                            |                      ~~--_  /      |                                |
		 *  |                            +---------------------------~~-------+                                |
		 *  +--------------------------------------------------------------------------------------------------+
		 *   - canvasContainer: The entire canvas container. Defines the visible area of the canvas.
		 *   - canvasWrap:      Scrolling content. Has width and height of the content after rotation.
		 *   - canvasWrapInner: Container for stacking multiple layers.
		 *  +-footerContainer----------------------------------------------------------------------------------+
		 *  |                                                                                                  |
		 *  |                                                                                                  |
		 *  |                                                                                                  |
		 *  |                                                                                                  |
		 *  |                                                                                                  |
		 *  +--------------------------------------------------------------------------------------------------+
		 */
		const adjustedCanvasSize = getAdjustedCanvasSize(zoomRatio, angle);
		let canvasContainerWidth, canvasContainerHeight;
		let canvasWrapWidth, canvasWrapHeight;
		let canvasWrapInnerWidth, canvasWrapInnerHeight;

		// compute canvas wrapper display size
		if (sessions.zoomFactor == 0) {
			// can extend the width to the max?
			if (canvasContainerWidthMax * (adjustedCanvasSize.height / adjustedCanvasSize.width) <= canvasContainerHeightMax) {
				zoomRatio = canvasContainerWidthMax / sessions.canvasWidth;

				canvasContainerWidth = canvasContainerWidthMax;
				canvasContainerHeight = canvasContainerWidthMax * (adjustedCanvasSize.height / adjustedCanvasSize.width);
			}
			// can extend the height to the max?
			else {
				zoomRatio = canvasContainerHeightMax / sessions.canvasHeight;

				canvasContainerWidth = canvasContainerHeightMax * (adjustedCanvasSize.width / adjustedCanvasSize.height);
				canvasContainerHeight = canvasContainerHeightMax;
			}

			const c2 = getAdjustedCanvasSize(zoomRatio, angle);
			canvasWrapWidth = c2.width;
			canvasWrapHeight = c2.height;

			canvasWrapInnerWidth = sessions.canvasWidth;
			canvasWrapInnerHeight = sessions.canvasHeight;
		}
		else {
			canvasWrapWidth = adjustedCanvasSize.width;
			canvasWrapHeight = adjustedCanvasSize.height;

			canvasContainerWidth = screenRect.width;
			canvasContainerHeight = Math.min(canvasContainerHeightMax, canvasWrapHeight);

			canvasWrapInnerWidth = sessions.canvasWidth;
			canvasWrapInnerHeight = sessions.canvasHeight;
		}

		/*
		 * canvas container
		 */

		//canvasContainer.style.width = canvasContainerWidth + 'px';
		canvasContainer.style.height = canvasContainerHeight + 'px';
		const toolboxHeight = Math.max(toolboxInnerRect.height + ZOOM_MARGIN, (screenRect.height - canvasContainerHeight) / 2);
		const footerHeight = screenRect.height - (toolboxHeight + canvasContainerHeight);
		toolboxContainer.style.height = toolboxHeight + 'px';
		footerContainer.style.height = footerHeight + 'px';

		/*
		 * canvas wrapper
		 */

		canvasWrap.style.width = canvasWrapWidth + 'px';
		canvasWrap.style.height = canvasWrapHeight + 'px';
		canvasWrap.style.left = (canvasContainerWidth / 2 - canvasWrapWidth / 2) + 'px';
		canvasWrap.style.top = (canvasContainerHeight / 2 - canvasWrapHeight / 2) + 'px';

		/*
		 * canvas inner wrapper
		 */

		canvasWrapInner.style.width = canvasWrapInnerWidth + 'px';
		canvasWrapInner.style.height = canvasWrapInnerHeight + 'px';
		updateCanvasTransform();

		/*
		 * layers
		 */

		for (let i = 1; i < 3; i++) {
			const canvas = canvases[`layer${i}`];
			const ctx = canvas.getContext('2d');
			canvas.style.left = getLayerOffsetX(canvas) + 'px';
			canvas.style.top = getLayerOffsetY(canvas) + 'px';
		}

		// termination
		$qsa(`[name="draw-zoom"][value="${sessions.zoomFactor}"]`, container).forEach(node => {
			node.checked = true;
		});

		enterBusy('setZoomFactor');
		return transitionendp(canvasWrap).then(() => {
			updateCursor();
			canvasRect = null;
			leaveBusy('setZoomFactor');
		});
	}

	function setLayerIndex (index) {
		sessions.layerIndex = Math.max(0, Math.min(index, 2));

		/*
		 * this function places the pen layer immediately after the active layer.
		 *
		 * setLayerIndex(0):
		 *
		 *   [0]     [0]     [0]
		 *   [1] --> [1] --> [p]
		 *   [2]     [2]     [1]
		 *   [p]             [2]
		 */
		const wrap = $qs('.canvas-wrap-inner', container);
		const targetCanvas = index < 3 ? canvases[`layer${index + 1}`] : null;
		const layerPen = wrap.removeChild(canvases.layerPen);
		wrap.insertBefore(layerPen, targetCanvas);

		for (let i = 0; i < 3; i++) {
			const canvas = canvases[`layer${i}`];
			if (i > 0 && i == sessions.layerIndex) {
				canvas.style.outline = LAYER_OUTLINE;
			}
			else {
				canvas.style.outline = '';
			}
		}

		$qsa(`[name="draw-layer"][value="${sessions.layerIndex}"]`, container).forEach(node => {
			node.checked = true;
		});

		context = null;
	}

	// <<<2 promise utilities

	function loadImage (source) {
		return new Promise((resolve, reject) => {
			const img = new Image;
			img.onload = () => {
				resolve(img);
				img.onload = img.onerror = null;
			};
			img.onerror = () => {
				reject();
				img.onload = img.onerror = null;
			};
			img.src = source;
		});
	}

	function delay (ms) {
		return new Promise(resolve => setTimeout(resolve, ms));
	}

	// <<<2 undo functions

	function pushUndo () {
		const undoItem = {
			time: new Date,
			sizes: [],
			offsets: [],
			layers: []
		};

		for (let i = 0; i < 3; i++) {
			const canvas = canvases[`layer${i}`];
			undoItem.sizes.push({
				width: canvas.width,
				height: canvas.height
			});
			undoItem.offsets.push({
				x: i == 0 ? null : getLayerOffsetX(canvas),
				y: i == 0 ? null : getLayerOffsetY(canvas)
			});
			undoItem.layers.push(canvas.toDataURL());
		}

		function isSame () {
			const isSizeDifferent = undoBuffer[undoPosition].sizes.some((o, i) => {
				return o.width != undoItem.sizes[i].width
					|| o.height != undoItem.sizes[i].height;
			});
			if (isSizeDifferent) {
				return false;
			}

			const isOffsetDifferent = undoBuffer[undoPosition].offsets.some((o, i) => {
				return o.x != undoItem.offsets[i].x
					|| o.y != undoItem.offsets[i].y;
			});
			if (isOffsetDifferent) {
				return false;
			}

			const isContentDifferent = undoBuffer[undoPosition].layers.some((l, i) => {
				return l != undoItem.layers[i];
			});
			if (isContentDifferent) {
				return false;
			}

			return true;
		}

		if (undoPosition >= 0 && isSame()) {
			return;
		}

		undoBuffer.length = undoPosition + 1;
		undoBuffer.push(undoItem);

		while (undoBuffer.length > UNDO_MAX) {
			undoBuffer.shift();
		}

		undoPosition = undoBuffer.length - 1;

		dumpUndoBuffer();
	}

	function undo (invert) {
		if (busy) return Promise.resolve();

		if (invert) {
			if (undoPosition >= undoBuffer.length - 1) return Promise.resolve();
			++undoPosition;
		}
		else {
			if (undoBuffer.length == 0) return Promise.resolve();
			if (undoPosition == 0) return Promise.resolve();
			--undoPosition;
		}

		enterBusy('undo');
		container.classList.remove('running');
		const undoItem = undoBuffer[undoPosition];
		return Promise.all(undoItem.layers.map(layer => loadImage(layer)))
			.then(images => {
				let resized = false;

				images.forEach((image, index) => {
					const canvas = canvases[`layer${index}`];

					resized = resized
						|| canvas.width != undoItem.sizes[index].width
						|| canvas.height != undoItem.sizes[index].height;

					// size
					canvas.width = undoItem.sizes[index].width;
					canvas.height = undoItem.sizes[index].height;

					// offset
					if (undoItem.offsets[index].x != null) {
						canvas.style.left = undoItem.offsets[index].x + 'px';
						setLayerOffsetX(canvas, undoItem.offsets[index].x);
					}
					if (undoItem.offsets[index].y != null) {
						canvas.style.top = undoItem.offsets[index].y + 'px';
						setLayerOffsetY(canvas, undoItem.offsets[index].y);
					}

					// content
					const ctx = canvas.getContext('2d');
					ctx.clearRect(0, 0, canvas.width, canvas.height);
					ctx.drawImage(image, 0, 0);
				});

				sessions.canvasWidth = canvases.layerPen.width = undoItem.sizes[0].width;
				sessions.canvasHeight = canvases.layerPen.height = undoItem.sizes[0].height;

				dumpUndoBuffer();

				return resized;
			})
			.then(resized => {
				if (resized) {
					return setZoomFactor(sessions.zoomFactor);
				}
			})
			.finally(() => {
				context = canvasRect = null;
				leaveBusy('undo');

				return delay(100).then(() => {
					container.classList.add('running');
				});
			});
	}

	function dumpUndoBuffer () {
		if (!DUMP_UNDO_BUFFER) return;

		const a = [];
		for (let i = 0; i < undoBuffer.length; i++) {
			const undoItem = undoBuffer[i];
			const b = [];
			for (let j = 0; j < 3; j++) {
				b.push(`${undoItem.sizes[j].width}x${undoItem.sizes[j].height}:${undoItem.offsets[j].x},${undoItem.offsets[j].y}`);
			}
			const line = `#${i}, ${b.join(' ')}, ${undoItem.time.toLocaleTimeString()}`;
			if (i == undoPosition) {
				a.push(`[${line}]`);
			}
			else {
				a.push(` ${line} `);
			}
		}
		conlog(`undo buffer:\n  ${a.join('\n  ')}`);
	}

	// <<<2 canvas manipulators

	function ensurePenContext () {
		if (!context) {
			context = canvases.layerPen.getContext('2d');
			initContext(context);
		}

		return context;
	}

	function ensureLayerContext (index) {
		if (!context) {
			context = canvases[`layer${sessions.layerIndex}`].getContext('2d');
			initContext(context);
		}

		return context;
	}

	function ensureCanvasRect () {
		if (!canvasRect) {
			canvasRect = {
				wrap: getBoundingClientRect($qs('.canvas-container', container)),
				canvas: getBoundingClientRect($qs('.canvas-wrap', container))
			};
			//dumpBoundingClientRect(canvasRect.wrap, 'wrap');
			//dumpBoundingClientRect(canvasRect.canvas, 'canvas');
		}

		return canvasRect;
	}

	function applyPoints () {
		const targetCanvas = canvases[`layer${sessions.layerIndex}`];
		const targetCtx = targetCanvas.getContext('2d');
		const resolutionFactor = globals.usePixelatedLines ? 1 : HIGH_RESOLUTION_FACTOR;
		initContext(targetCtx);

		const ctx = canvases.hd.getContext('2d');
		initContext(ctx);
		ctx.clearRect(0, 0, canvases.hd.width, canvases.hd.height);

		const {offsetX: layerOffsetX, offsetY: layerOffsetY} = getLayerOffset(targetCanvas);
		const penOffset = (globals.penSize % 2) / 2.0;

		points.__momocanLineWidth__ = globals.penSize;
		points.__momocanInterpolateScale__ = globals.interpolateScale;

		if (globals.enablePenAdjustment && globals.interpolateScale > 0) {
			/*
			 * create interpolated points
			 */

			const DEGREE_THRESHOLD = 4 + globals.interpolateScale * 12;
			const DISTANCE_THRESHOLD = 4 + globals.interpolateScale * 6;
			const DISTANCE_LIMIT = DISTANCE_THRESHOLD * 2;

			const interpolatePoints = [];
			const last = points.pop();
			let lastPoint = points[0];
			let lastDegree = NaN;

			interpolatePoints.push([
				(points[0][0] + penOffset) * resolutionFactor,
				(points[0][1] + penOffset) * resolutionFactor]);
			for (let i = 1, prevDeg = NaN; i < points.length; i++) {
				const prev = points[i - 1];
				const current = points[i];
				const distance = Math.sqrt(Math.pow(current[0] - lastPoint[0], 2) + Math.pow(current[1] - lastPoint[1], 2));
				const degree = Math.atan2(prev[1] - current[1], current[0] - prev[0]) * 180 / Math.PI;
				const degreeDeltaP = Math.abs(degree - prevDeg);
				const degreeDeltaL = Math.abs(degree - lastDegree);

				let hit = false;
				if (degreeDeltaP >= DEGREE_THRESHOLD) {
					hit = true;
					current.push(`1: degree delta: ${degreeDeltaP}`);
				}
				else if (degreeDeltaL >= DEGREE_THRESHOLD && distance >= DISTANCE_THRESHOLD) {
					hit = true;
					current.push(`2: degree delta: ${degreeDeltaL}, distance: ${distance}`);
				}
				else if (distance >= DISTANCE_LIMIT) {
					hit = true;
					current.push(`3: distance: ${DISTANCE_LIMIT} from (${lastPoint[0]}, ${lastPoint[1]})`);
				}

				if (hit) {
					lastPoint = [current[0], current[1]];
					lastDegree = degree;
					interpolatePoints.push([
						current[0] * resolutionFactor,
						current[1] * resolutionFactor]);
				}

				prevDeg = degree;
			}
			points.push(last);
			interpolatePoints.push([
				(last[0] + penOffset) * resolutionFactor,
				(last[1] + penOffset) * resolutionFactor]);

			/*
			 * draw entire line with pointed end
			 */

			const DECAY_THRESHOLD = 0.5;
			const RELEASE_LINE_WIDTH_FACTOR = 0.3;
			const LINE_RESOLUTION = 10;
			const EMULATE_POINTED_END_THRESHOLD_MSECS = 100;

			const totalDistance = interpolatePoints.reduce((result, c, i, p) => {
				const subDistance = i == 0 ?
					0 :
					Math.sqrt(Math.pow(p[i][0] - p[i - 1][0], 2) + Math.pow(p[i][1] - p[i - 1][1], 2));
				return result + subDistance;
			}, 0);
			const thresholdDistance = totalDistance * DECAY_THRESHOLD;
			const s = Smooth(interpolatePoints, {
				method: 'cubic',
				clip: 'clamp',
				cubicTension: 0
			});
			const unit = 1 / LINE_RESOLUTION;

			let currentDistance = 0;
			let [prevx, prevy] = interpolatePoints[0];
			let enableEmulatePointedEnd = globals.emulatePointedEnd
				&& points[points.length - 1][4] - points[points.length - 2][4] < EMULATE_POINTED_END_THRESHOLD_MSECS;

			if (globals.usePixelatedLines) {
				let currentLineWidth = globals.penSize;
				for (let index = unit; index <= interpolatePoints.length - 1; index += unit) {
					const [x, y] = s(index);
					line(ctx, prevx, prevy, x, y, currentLineWidth);
					currentDistance += Math.sqrt(Math.pow(x - prevx, 2) + Math.pow(y - prevy, 2));
					prevx = x;
					prevy = y;

					if (enableEmulatePointedEnd && currentDistance >= thresholdDistance) {
						const decayRange = totalDistance - thresholdDistance;
						if (decayRange > 0) {
							const lineWidthFactor =
								(totalDistance - currentDistance)
								/ decayRange
								* (1.0 - RELEASE_LINE_WIDTH_FACTOR)
								+ RELEASE_LINE_WIDTH_FACTOR;
							currentLineWidth = globals.penSize * resolutionFactor * lineWidthFactor;
						}
					}
				}

				line(
					ctx,
					prevx, prevy,
					s(interpolatePoints.length)[0], s(interpolatePoints.length)[1],
					currentLineWidth);
				targetCtx.drawImage(
					ctx.canvas,
					0, 0, sessions.canvasWidth, sessions.canvasHeight,
					-layerOffsetX, -layerOffsetY, sessions.canvasWidth, sessions.canvasHeight);
			}
			else {
				ctx.beginPath();
				ctx.lineWidth = globals.penSize * resolutionFactor;
				ctx.moveTo(prevx, prevy);
				for (let index = unit; index <= interpolatePoints.length - 1; index += unit) {
					const [x, y] = s(index);
					ctx.lineTo(x, y);
					currentDistance += Math.sqrt(Math.pow(x - prevx, 2) + Math.pow(y - prevy, 2));
					prevx = x;
					prevy = y;

					if (enableEmulatePointedEnd && currentDistance >= thresholdDistance) {
						const decayRange = totalDistance - thresholdDistance;
						if (decayRange > 0) {
							const lineWidthFactor =
								(totalDistance - currentDistance)
								/ decayRange
								* (1.0 - RELEASE_LINE_WIDTH_FACTOR)
								+ RELEASE_LINE_WIDTH_FACTOR;
							ctx.stroke();
							ctx.lineWidth = globals.penSize * resolutionFactor * lineWidthFactor;
							ctx.beginPath();
							ctx.moveTo(x, y);
						}
					}
				}

				/*
				ctx.lineTo(
					s(interpolatePoints.length)[0],
					s(interpolatePoints.length)[1]);
				*/
				ctx.stroke();

				/*
				 * draw high resolution canvas into target canvas
				 */

				targetCtx.save();
				targetCtx.imageSmoothingEnabled = true;
				targetCtx.imageSmoothingQuality = 'high';
				targetCtx.drawImage(
					ctx.canvas,
					0, 0, canvases.hd.width, canvases.hd.height,
					-layerOffsetX, -layerOffsetY, canvases.hd.width / HIGH_RESOLUTION_FACTOR, canvases.hd.height / HIGH_RESOLUTION_FACTOR);
				targetCtx.restore();
			}
		}
		else {
			if (globals.usePixelatedLines) {
				let prevx = points[0][0];
				let prevy = points[0][1];
				for (let i = 1; i < points.length; i++) {
					const currentx = points[i][0];
					const currenty = points[i][1];
					line(ctx, prevx, prevy, currentx, currenty, globals.penSize);
					prevx = currentx;
					prevy = currenty;
				}
			}
			else {
				ctx.beginPath();
				ctx.moveTo(
					points[0][0] + penOffset,
					points[0][1] + penOffset);
				for (let i = 1; i < points.length; i++) {
					ctx.lineTo(
						points[i][0] + penOffset,
						points[i][1] + penOffset);
				}
				ctx.stroke();
			}

			targetCtx.drawImage(
				ctx.canvas,
				0, 0, sessions.canvasWidth, sessions.canvasHeight,
				-layerOffsetX, -layerOffsetY, sessions.canvasWidth, sessions.canvasHeight);
		}
	}

	function plotPoints () {
		const points = [[11.5,9.5],[12.5,9.5],[12.5,9.5],[12.5,9.5],[15.5,12.5],[17.5,16.5],[22.5,24.5],[29.5,32.5],[35.5,41.5],[41.5,51.5],[48.5,59.5],[57.5,72.5],[64.5,79.5],[69.5,84.5],[74.5,88.5],[75.5,88.5],[78.5,87.5],[82.5,83.5],[85.5,77.5],[89.5,71.5],[91.5,67.5],[92.5,65.5],[92.5,62.5],[93.5,61.5],[93.5,59.5],[94.5,56.5],[94.5,54.5],[94.5,52.5]];

		const targetCanvas = canvases[`layer0`];
		const ctx = targetCanvas.getContext('2d');
		initContext(ctx);
		ctx.save();
		try {
			// test paintings goes here...
		}
		finally {
			ctx.restore();
		}
	}

	function updateCursor (size) {
		const wrap = $qs('.canvas-wrap', container);

		if (typeof size == 'undefined') {
			switch (penMode) {
			case 0: size = globals.penSize; break;
			case 1: size = globals.eraserSize; break;
			}
		}

		if (typeof size == 'undefined') {
			return;
		}

		const cursor = canvases.cursor;
		cursor.width = cursor.height = 32 * 4;

		const w = cursor.width;
		const h = cursor.height;
		const ctx = cursor.getContext('2d');
		ctx.clearRect(0, 0, w, h);

		ctx.strokeStyle = '#ffffff';
		ctx.lineWidth = 3;
		ctx.beginPath();
		ctx.arc(w / 2 + 0.5, h / 2 + 0.5, size * zoomRatio / 2, 0, 2 * Math.PI);
		ctx.closePath();
		ctx.stroke();

		ctx.strokeStyle = '#000000';
		ctx.lineWidth = 1;
		ctx.beginPath();
		ctx.arc(w / 2 + 0.5, h / 2 + 0.5, size * zoomRatio / 2, 0, 2 * Math.PI);
		ctx.closePath();
		ctx.stroke();

		if (globals.useCrossCursor) {
			ctx.fillStyle = '#ffffff';
			ctx.fillRect(w / 2 - 3 - 6, h / 2 - 1,     7, 3);
			ctx.fillRect(w / 2 + 3,     h / 2 - 1,     7, 3);
			ctx.fillRect(w / 2 - 1,     h / 2 - 3 - 6, 3, 7);
			ctx.fillRect(w / 2 - 1,     h / 2 + 3,     3, 7);

			ctx.fillStyle = '#000000';
			ctx.fillRect(w / 2 - 3 - 5, h / 2,         5, 1);
			ctx.fillRect(w / 2 + 4,     h / 2,         5, 1);
			ctx.fillRect(w / 2,         h / 2 - 3 - 5, 1, 5);
			ctx.fillRect(w / 2,         h / 2 + 4,     1, 5);
		}
	}

	function updateCanvasTransform (a, z) {
		if (isNaN(a)) a = angle;
		if (isNaN(z)) z = zoomRatio;
		const canvasWrapInner = $qs('.canvas-wrap-inner', container);
		canvasWrapInner.style.transform = `rotate(${a}deg) scale(${z})`;
	}

	function initContext (ctx) {
		ctx.globalCompositeOperation = 'source-over';
		ctx.lineCap = ctx.lineJoin = 'round';
		ctx.strokeStyle = ctx.fillStyle = sessions.foregroundColor;
		ctx.lineWidth = globals.penSize;
	}

	function fillCanvasWithSingleColor (canvas, color) {
		const ctx = canvas.getContext('2d');
		ctx.save();
		ctx.globalCompositeOperation = 'source-in';
		ctx.fillStyle = color;
		ctx.fillRect(0, 0, canvas.width, canvas.height);
		ctx.restore();
	}

	function updateCrispPenColor (color) {
		fillCanvasWithSingleColor(canvases.crispPen, color || sessions.foregroundColor);
	}

	function updateCrispEraserColor (color) {
		fillCanvasWithSingleColor(canvases.crispEraser, color || sessions.backgroundColor);
	}

	function initCrispPen () {
		const canvas = document.createElement('canvas');
		canvas.width = (PEN_SIZE_MAX + 1) * PEN_SIZE_MAX;
		canvas.height = PEN_SIZE_MAX;

		const ctx = canvas.getContext('2d');
		ctx.fillStyle = '#000000';
		for (let i = 1; i <= PEN_SIZE_MAX; i++) {
			fillCircle(ctx, i * PEN_SIZE_MAX + PEN_SIZE_MAX / 2, PEN_SIZE_MAX / 2, i);
		}

		return canvas;
	}

	function filterBase (callback) {
		for (let i = 0; i < 3; i++) {
			const canvas = canvases[`layer${i}`];
			const tmp = document.createElement('canvas');

			tmp.width = canvas.width;
			tmp.height = canvas.height;
			tmp.getContext('2d').drawImage(canvas, 0, 0);

			const ctx = canvas.getContext('2d');
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			ctx.save();
			callback(ctx, tmp, i);
			ctx.restore();
		}
	}

	function rotateImage (isRight) {
		container.classList.remove('running');
		for (let i = 0; i < 3; i++) {
			const dstCanvas = canvases[`layer${i}`];
			const srcCanvas = document.createElement('canvas');

			srcCanvas.width = dstCanvas.width;
			srcCanvas.height = dstCanvas.height;
			srcCanvas.getContext('2d').drawImage(dstCanvas, 0, 0);

			dstCanvas.width = srcCanvas.height;
			dstCanvas.height = srcCanvas.width;

			const ctx = dstCanvas.getContext('2d');
			ctx.clearRect(0, 0, dstCanvas.width, dstCanvas.height);
			ctx.save();
			ctx.translate(dstCanvas.width / 2, dstCanvas.height / 2);
			ctx.rotate((isRight ? 90 : -90) * Math.PI / 180);
			ctx.drawImage(srcCanvas, -srcCanvas.width / 2, -srcCanvas.height / 2);
			ctx.restore();

			if (i == 0) {
				sessions.canvasWidth = canvases.layerPen.width = srcCanvas.height;
				sessions.canvasHeight = canvases.layerPen.height = srcCanvas.width;

				canvases.hd.width = srcCanvas.height * HIGH_RESOLUTION_FACTOR;
				canvases.hd.height = srcCanvas.width * HIGH_RESOLUTION_FACTOR;
			}
			else {
				const {offsetX, offsetY} = getLayerOffset(dstCanvas);
				let newOffsetX, newOffsetY;

				if (isRight) {
					// This code should refer to the sessions.canvasHeight, but since
					// the width and height are swapped in the layer#0 loop, therefore
					// we refer to the sessions.canvasWidth.
					newOffsetX = sessions.canvasWidth - (offsetY + dstCanvas.height);
					newOffsetY = offsetX;
				}
				else {
					newOffsetX = offsetY;
					// This code should refer to the sessions.canvasWidth, but since
					// the width and height are swapped in the layer#0 loop, therefore
					// we refer to the sessions.canvasHeight.
					newOffsetY = sessions.canvasHeight - (offsetX + dstCanvas.width);
				}

				dstCanvas.style.left = newOffsetX + 'px';
				dstCanvas.style.top = newOffsetY + 'px';
				setLayerOffset(dstCanvas, newOffsetX, newOffsetY);
			}
		}

		context = canvasRect = null;
		return setZoomFactor(sessions.zoomFactor)
			.then(() => delay(100))
			.then(() => {
				container.classList.add('running');
			});
	}

	function floodColor (startX, startY, color) {
		// @see https://github.com/williammalone/HTML5-Paint-Bucket-Tool

		startX = Math.floor(startX);
		startY = Math.floor(startY);
		if (startX < 0 || startX >= sessions.canvasWidth
		||  startY < 0 || startY >= sessions.canvasHeight) return;

		const srcCtx = (globals.sampleMerged ?
			mergeLayers() :
			mergeLayers(sessions.layerIndex, sessions.layerIndex + 1)).getContext('2d');
		const width = srcCtx.canvas.width;
		const height = srcCtx.canvas.height;
		// those must be the same logically
		if (width != sessions.canvasWidth
		||  height != sessions.canvasHeight) return;

		const src = srcCtx.getImageData(0, 0, width, height);
		const [startR, startG, startB] = subset(src.data, (startY * width + startX) << 2, 3);
		const [, fillR, fillG, fillB] = /#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i
			.exec(setColor(null, color))
			.map(a => parseInt(a, 16));
		if (startR == fillR && startG == fillG && startB == fillB) return;

		const dstCtx = canvases[`layer${sessions.layerIndex}`].getContext('2d');
		const {offsetX, offsetY} = getLayerOffset(dstCtx.canvas);
		const dst = dstCtx.getImageData(-offsetX, -offsetY, width, height);

		function isClosedColor (r1, g1, b1, r2, g2, b2) {
			// @see https://en.wikipedia.org/wiki/Color_difference
			const delta = Math.sqrt(
				2 * Math.pow(r1 - r2, 2) +
				4 * Math.pow(g1 - g2, 2) +
				3 * Math.pow(b1 - b2, 2)) / 765;
			return delta <= globals.closedColorThreshold;
		}

		function isInRegion (pos) {
			const r = src.data[pos + 0];
			const g = src.data[pos + 1];
			const b = src.data[pos + 2];
			if (r == fillR && g == fillG && b == fillB) {
				return false;
			}
			else {
				return isClosedColor(r, g, b, startR, startG, startB);
			}
		}

		const width4 = width << 2;
		const overfillSize = globals.overfillSize + 1;
		const crispCanvas = canvases.crispFlood;
		const lineOpts = {size: overfillSize, penCanvas: crispCanvas};
		const stack = [[startX, startY]];

		let sentinel = width * height;
		fillCanvasWithSingleColor(crispCanvas, sessions.foregroundColor);
		dstCtx.fillStyle = sessions.foregroundColor;

		while (sentinel && stack.length) {
			let [x, y] = stack.pop();
			let pos = (y * width + x) << 2;
			while (y >= 0 && isInRegion(pos)) {
				y--;
				pos -= width4;
			}
			y++;
			pos += width4;

			let left = false;
			let right = false;
			while (y <= height - 1 && isInRegion(pos)) {
				src.data[pos + 0] = dst.data[pos + 0] = fillR;
				src.data[pos + 1] = dst.data[pos + 1] = fillG;
				src.data[pos + 2] = dst.data[pos + 2] = fillB;
				src.data[pos + 3] = dst.data[pos + 3] = 255;

				if (overfillSize >= 2) {
					if (globals.usePixelatedLines) {
						line(dstCtx, x - offsetX, y - offsetY, x - offsetX, y - offsetY, lineOpts);
					}
					else {
						dstCtx.beginPath();
						dstCtx.arc(x - offsetX + 0.5, y - offsetY + 0.5, overfillSize, 0, 2 * Math.PI);
						dstCtx.fill();
					}
				}

				sentinel--;

				if (x > 0) {
					if (isInRegion(pos - 4)) {
						if (!left) {
							stack.push([x - 1, y]);
							left = true;
						}
					}
					else if (left) {
						left = false;
					}
				}

				if (x < width - 1) {
					if (isInRegion(pos + 4)) {
						if (!right) {
							stack.push([x + 1, y]);
							right = true;
						}
					}
					else if (right) {
						right = false;
					}
				}

				y++;
				pos += width4;
			}

			/*
			 * for debug
			 */
			//dstCtx.putImageData(dst, -offsetX, -offsetY);
		}

		if (overfillSize < 2) {
			dstCtx.putImageData(dst, -offsetX, -offsetY);
		}
	}

	function mergeLayers (start, end) {
		const result = document.createElement('canvas');
		start || (start = 0);
		end || (end = 3);
		result.width = sessions.canvasWidth;
		result.height = sessions.canvasHeight;

		for (let i = start; i < end; i++) {
			const canvas = canvases[`layer${i}`];
			const {offsetX, offsetY} = getLayerOffset(canvas);
			result.getContext('2d').drawImage(canvas, offsetX, offsetY);
		}

		return result;
	}

	function fillCircle (ctx, centerX, centerY, size) {
		size = Math.floor(size);
		if (size < 1) return;
		const isEven = size % 2 == 0 ? 1 : 0;
		const radius = Math.floor(size / 2);
		let x = radius;
		let y = 0;
		let f = -2 * radius + 3;
		centerX = Math.floor(centerX);
		centerY = Math.floor(centerY);
		while (x >= y) {
			ctx.fillRect(centerX - x + isEven, centerY - y + isEven, x * 2 + 1 - isEven, 1);
			ctx.fillRect(centerX - y + isEven, centerY - x + isEven, y * 2 + 1 - isEven, 1);

			ctx.fillRect(centerX - x + isEven, centerY + y, x * 2 + 1 - isEven, 1);
			ctx.fillRect(centerX - y + isEven, centerY + x, y * 2 + 1 - isEven, 1);

			if (f >= 0) {
				x -= 1;
				f -= 4 * x;
			}
			y += 1;
			f += 4 * y + 2;
		}
		//ctx.fillStyle = 'rgba(0,255,0,0.5)';
		//ctx.fillRect(centerX - radius + isEven, centerY - radius + isEven, size, size);
	}

	function line (ctx, x0, y0, x1, y1, opts) {
		x0 = Math.floor(x0);
		y0 = Math.floor(y0);
		x1 = Math.floor(x1);
		y1 = Math.floor(y1);

		let size, penCanvas;
		if (typeof opts == 'number') {
			size = opts;
		}
		else if (typeof opts == 'object') {
			size = opts.size;
			penCanvas = opts.penCanvas;
		}
		size = minmax(1, Math.floor(size), PEN_SIZE_MAX);
		penCanvas = penCanvas || canvases.crispPen;

		const signx = x1 > x0 ? 1 : x1 < x0 ? -1 : 0;
		const signy = y1 > y0 ? 1 : y1 < y0 ? -1 : 0;
		const dx = Math.abs(x1 - x0);
		const dy = Math.abs(y1 - y0);
		const px = size * PEN_SIZE_MAX;
		const pen_size_half = Math.floor(PEN_SIZE_MAX / 2);

		if (dx > dy) {
			// line in landscape box
			const dx2 = dx * 2;
			const dy2 = dy * 2;
			let e = -dx;
			let n = dx;
			let x = x0;
			let y = y0;
			do {
				ctx.drawImage(
					penCanvas,
					px, 0, PEN_SIZE_MAX, PEN_SIZE_MAX,
					x - pen_size_half, y - pen_size_half, PEN_SIZE_MAX, PEN_SIZE_MAX);
				x += signx;
				e += dy2;
				if (e >= 0) {
					y += signy;
					e -= dx2;
				}
			} while (--n >= 0);
		}
		else {
			// line in portrait box
			const dx2 = dx * 2;
			const dy2 = dy * 2;
			let e = -dy;
			let n = dy;
			let x = x0;
			let y = y0;
			do {
				ctx.drawImage(
					penCanvas,
					px, 0, PEN_SIZE_MAX, PEN_SIZE_MAX,
					x - pen_size_half, y - pen_size_half, PEN_SIZE_MAX, PEN_SIZE_MAX);
				y += signy;
				e += dx2;
				if (e >= 0) {
					x += signx;
					e -= dy2;
				}
			} while (--n >= 0);
		}
	}

	// <<<2 popups

	function startSettingsDialog () {
		const settings = $qs('.settings-container', container);
		const wrap = $qs('.settings-wrap', settings);
		settings.classList.remove('hide');
		canvases.cursor.style.display = 'none';
		overlaid = true;
		return delay(100)
			.then(() => {
				settings.classList.add('running');
				return transitionendp(wrap);
			})
			.then(() => {
				return new Promise(resolve => {
					clickDispatcher.add('#draw-settings-close', () => {
						clickDispatcher.remove('#draw-settings-close');
						settings.classList.remove('running');
						resolve(transitionendp(wrap).then(() => {
							settings.classList.add('hide');
							canvases.cursor.style.display = '';
							overlaid = false;
						}));
					});
				});
			});
	}

	function startColorPicker (target) {
		target.setAttribute('data-palette-saved', target.getAttribute('data-palette'));
		canvases.cursor.style.display = 'none';
		overlaid = true;
		return new Promise(resolve => {
			colorPicker(target, {
				initialColor: target.getAttribute('data-palette'),
				change: color => {
					target.style.background = color.text;
				},
				ok: color => {
					setColor(target, color.text);
					if (/#fg-color/.test(target.href)) {
						sessions.foregroundColor = color.text;
						updateCursor();
					}
					else if (/#bg-color/.test(target.href)) {
						sessions.backgroundColor = color.text;
					}
					else if (target.hasAttribute('data-index')) {
						const index = target.getAttribute('data-index') - 0;
						globals.palettes[index] = sessions.foregroundColor = color.text;
						setColor($qs('.current-color [href="#fg-color"]', container), color.text);
						updateCursor();
					}

					context = null;
					resolve(color);
				},
				cancel: () => {
					target.style.backgroundColor = target.getAttribute('data-palette-saved');
					resolve();
				},
				close: () => {
					target.removeAttribute('data-palette-saved');
					canvases.cursor.style.display = '';
					overlaid = false;
				}
			});
		});
	}

	function startPopupMenu (target, items) {
		canvases.cursor.style.display = 'none';
		overlaid = true;
		return new Promise(resolve => {
			popupMenu(target, {
				items: items,
				ok: anchor => {
					resolve(anchor);
				},
				cancel: () => {
					resolve();
				},
				close: () => {
					canvases.cursor.style.display = '';
					clickDispatcher.enabled = true;
					overlaid = false;
				}
			});
		});
	}

	function startDrawMethodOption (target, targetOption) {
		canvases.cursor.style.display = 'none';
		overlaid = true;
		return new Promise(resolve => {
			let transport;
			let base = popup(target, {
				root: targetOption.parentElement,
				createPanel: t => {
					transport = t;

					t.panel.style.padding = '4px';
					t.panel.style.backgroundColor = t.panel.style.borderColor = '#ffffee';
					t.panel.style.color = '#800000';

					const cloned = targetOption.cloneNode(true);
					cloned.classList.remove('hide');
					cloned.style.minWidth = targetOption.parentElement.style.width;
					t.panel.appendChild(cloned);
				},
				cancel: t => {
					resolve();
				},
				close: t => {
					canvases.cursor.style.display = '';
					overlaid = false;
					base = transport = null;
				}
			});
		});
	}

	// <<<2 event handlers

	function handlePointerDown (e) {
		pointerInfo.buttons = 0;

		if (e.buttons != 1) {
			if (e.buttons == 2 && pointerInfo.drag.kind == '') {
				ensureCanvasRect();
				container.classList.remove('running');
				const {x, y} = getAdjustedPosition(e.clientX, e.clientY, 0);
				Object.assign(pointerInfo.drag, {
					kind: 'right-click',
					state: 1,
					baseLeft: canvasRect.canvas.left - canvasRect.wrap.left,
					baseTop: canvasRect.canvas.top - canvasRect.wrap.top,
					baseAngle: angle,
					startX: e.clientX,
					startY: e.clientY,
					startAngle: Math.floor(Math.atan2(y - canvases.layer0.height / 2, x - canvases.layer0.width / 2) * 180 / Math.PI),
					outRanged: false
				});
				//container.setPointerCapture(pointerInfo.captureId = e.pointerId);
				$qs('.tips .normal', container).classList.add('hide');
				$qs('.tips .right-click', container).classList.remove('hide');
			}
			return;
		}
		if (busy) return;
		if (repeatMap[' ']) return;

		clickDispatcher.enabled = true;

		const path = getEventPath(e);
		if (/ (?:input|button)\b/.test(path)) return;
		if (/\.(?:toolbox)[. ]/.test(path)) {
			lookupLongPressHandler(e);
			return;
		}

		e.preventDefault();
		e.stopPropagation();
		container.setPointerCapture(pointerInfo.captureId = e.pointerId);

		points.length = 0;

		let x, y;
		let profile = [];
		if (elasticInfo.active) {
			[x, y] = elasticInfo.head;
			points.push(elasticInfo.head);
			profile.push(elasticInfo.context);
		}
		else {
			[x, y] = getCanvasPos(e);

			const firstKeys = ['draw', 'erase', 'flood', 'select', 'lasso', 'move'];
			if (e.ctrlKey) {
				profile.push('colorpicker');
			}
			else if (penMode <= 1) {
				profile.push(firstKeys[penMode]);
				profile.push(globals.usePixelatedLines ? 'pixel' : 'pen');
				profile.push(sessions.layerIndex ? 'layer': 'background');
			}
			else {
				profile.push(firstKeys[penMode]);
			}

			container.addEventListener(pointerEventNames.move, handleDrawingPointerMove);
		}
		profile = profile.join('_');
		drawFunction = drawFunctions[profile];
		if (profile != contextProfile) {
			context = null;
		}
		contextProfile = profile;

		drawFunction.down(x, y, e);
		container.addEventListener(pointerEventNames.up, handleDrawingPointerUp);
	}

	function handlePointerMove (e) {
		pointerInfo.clientX = e.clientX;
		pointerInfo.clientY = e.clientY;
		pointerInfo.buttons |= e.buttons;

		// fix incoherent shift key state
		if (repeatMap.Shift && !e.shiftKey) {
			handleWindowKeyup({key: 'Shift'});
		}

		ensureCanvasRect();

		// dragging
		if (pointerInfo.drag.kind != '') {
			switch (pointerInfo.drag.kind) {
			case 'scroll':
				{
					if (pointerInfo.drag.state == 0) {
						Object.assign(pointerInfo.drag, {
							state: 1,
							baseLeft: canvasRect.canvas.left - canvasRect.wrap.left,
							baseTop: canvasRect.canvas.top - canvasRect.wrap.top,
							startX: e.clientX,
							startY: e.clientY
						});
					}

					const wrap = $qs('.canvas-wrap', container);

					if (canvasRect.canvas.width > canvasRect.wrap.width) {
						const left = Math.floor(minmax(
							canvasRect.wrap.width - canvasRect.canvas.width,
							pointerInfo.drag.baseLeft + (e.clientX - pointerInfo.drag.startX),
							0));
						wrap.style.left = left + 'px';
					}

					if (canvasRect.canvas.height > canvasRect.wrap.height) {
						const top = Math.floor(minmax(
							canvasRect.wrap.height - canvasRect.canvas.height,
							pointerInfo.drag.baseTop + (e.clientY - pointerInfo.drag.startY),
							0));
						wrap.style.top = top + 'px';
					}
				}
				break;

			case 'right-click':
				{
					const {x, y} = getAdjustedPosition(e.clientX, e.clientY, 0);
					const currentAngle = regalizeAngle(
						Math.floor(Math.atan2(y - canvases.layer0.height / 2, x - canvases.layer0.width / 2) * 180 / Math.PI));

					let computedAngle = regalizeAngle(
						pointerInfo.drag.baseAngle + (currentAngle - pointerInfo.drag.startAngle));

					if (Math.abs(computedAngle) < 3) {
						if (pointerInfo.drag.outRanged) {
							computedAngle = 0;
						}
					}
					else {
						pointerInfo.drag.outRanged = true;
					}
					angle = computedAngle;
					updateStatus();
					updateHUD(`${computedAngle}°`);
					updateCanvasTransform();
				}
				break;
			}

			canvasRect = null;
			ensureCanvasRect();
		}

		/*
		dumpBoundingClientRect(canvasRect.wrap, 'wrap');
		dumpBoundingClientRect(canvasRect.canvas, 'canvas');
		console.log(`${e.clientX}, ${e.clientY}`);
		*/

		// pen size indicating canvas
		let left, top, cursor;
		if (penMode <= 1 && !overlaid
		&&  e.clientX >= canvasRect.canvas.left && e.clientX < canvasRect.canvas.right
		&&  e.clientY >= canvasRect.wrap.top && e.clientY < canvasRect.wrap.bottom) {
			left = (e.clientX - canvases.cursor.width / 2) + 'px';
			top = (e.clientY - canvases.cursor.height / 2) + 'px';
			cursor = 'none';
		}
		else {
			left = -canvases.cursor.width + 'px';
			top = -canvases.cursor.height + 'px';
			cursor = '';
		}
		if (left != canvases.cursor.style.left || top != canvases.cursor.style.top) {
			canvases.cursor.style.left = left;
			canvases.cursor.style.top = top;
			container.style.cursor = cursor;
		}
	}

	function handlePointerUp (e) {
		if (pointerInfo.captureId != null) {
			container.releasePointerCapture(pointerInfo.captureId);
			pointerInfo.captureId = null;
		}

		if (pointerInfo.drag.kind != '') {
			switch (pointerInfo.drag.kind) {
			case 'right-click':
				{
					releaseHUD();
					canvases.cursor.style.display = '';
					$qs('.tips .normal', container).classList.remove('hide');
					$qs('.tips .right-click', container).classList.add('hide');

					container.classList.add('running');

					if (pointerInfo.drag.baseAngle != angle) {
						setZoomFactor(zoomRatio, true);
					}

					pointerInfo.drag.kind = '';
					pointerInfo.drag.state = -1;
				}
				break;
			}
		}
	}

	function handleDrawingPointerMove (e) {
		const [x, y] = getCanvasPos(e);
		if (x != undefined && y != undefined) {
			drawFunction.move(x, y);
		}
	}

	function handleElasticPointerMove (e) {
		const {x, y} = getAdjustedPosition(e.clientX, e.clientY, angle);
		if (x != undefined && y != undefined) {
			drawFunction.elastic(x, y);
		}
	}

	function handleDrawingPointerUp (e) {
		const [x, y, nativex, nativey] = getCanvasPos(e, true);

		// use a native position for last point, not a computed position
		points[points.length - 1][0] = nativex;
		points[points.length - 1][1] = nativey;

		if (elasticInfo.active) {
			elasticInfo.deactivate();
		}

		drawFunction.up(nativex, nativey);
		pushUndo();
		canvases.layerPen.getContext('2d').clearRect(0, 0, canvases.layerPen.width, canvases.layerPen.height);

		if (penMode <= 1 && pointerInfo.buttons == 3) {
			elasticInfo.activate();
		}

		if (pointerInfo.captureId != null) {
			container.releasePointerCapture(pointerInfo.captureId);
			pointerInfo.captureId = null;
		}

		pointerInfo.drag.kind = '';
		pointerInfo.drag.state = -1;

		container.removeEventListener(pointerEventNames.move, handleDrawingPointerMove);
		container.removeEventListener(pointerEventNames.up, handleDrawingPointerUp);
	}

	function handleTouchstart (e) {
		touchEnabled = true;
	}

	function handleTouchmove (e) {
		e.preventDefault();
	}

	function handlePaste (e) {
		if (e.target == document.body) {
			conlog('paste event on document.body');
		}
	}

	function handleWindowUnload (e) {
		exportCanvas();
		window.localStorage.setItem(GLOBAL_PERSIST_KEY, JSON.stringify(globals));
	}

	function handleWindowResize (e) {
		canvasRect = null;
	}

	function handleWindowFocusin (e) {
		/*
		 * input-range element uses drag operation, so exclude it
		 */

		if (/^input$/i.test(e.target.nodeName) && /^range$/i.test(e.target.type)) {
			return;
		}

		/*
		 * exclude text input elements
		 */

		if (isTextInputElement(e.target)) {
			return;
		}

		/*
		 * force the focus off from active element
		 */

		if (e.target.relatedTarget) {
			e.target.relatedTarget.focus();
		}
		else {
			e.target.blur();
		}
	}

	function handleWindowKeydown (e) {
		const isRepeated = e.key in repeatMap;
		repeatMap[e.key] = 1;
		//if (!isRepeated) conlog(e.key);

		if (busy) return;
		if (overlaid) return;

		let key = [];
		//e.shiftKey && key.push('s');
		e.ctrlKey && key.push('c');
		e.altKey && key.push('a');
		key.push(e.key);
		key = key.join('-');

		// modifier: pen mode invertion
		if (e.key == 'Shift' && !isRepeated) {
			switch (penMode) {
			case 0: case 1:
				setPenMode(1 - penMode);
				setPenSize(globals.penSize, globals.eraserSize, true);
				break;
			case 2:
				break;
			}
		}

		// other shortcuts
		else if (key in keyboardShortcuts) {
			e.preventDefault();
			e.stopPropagation();
			const o = keyboardShortcuts[key];
			if (typeof o == 'function') {
				keyboardShortcuts[key](e, isRepeated);
			}
			else if (/^#.+$/.test(o)) {
				clickDispatcher.dispatch({target: container}, container, o);
			}
		}
	}

	function handleWindowKeyup (e) {
		delete repeatMap[e.key];

		/*
		 * remove focus from an active element to prevent keyboard malfunction
		 */

		if (document.activeElement != document.body && !isTextInputElement(document.activeElement)) {
			document.activeElement.blur();
			document.body.focus();
		}

		if (e.key == 'Shift' && !overlaid) {
			switch (penMode) {
			case 0: case 1:
				setPenMode(1 - penMode);
				setPenSize(globals.penSize, globals.eraserSize, true);
				break;
			case 2:
				break;
			}
		}

		else if (e.key == ' ' && !overlaid) {
			pointerInfo.drag.kind = '';
			pointerInfo.drag.state = -1;
			canvasRect = null;
			container.classList.add('running');
		}
	}

	function handleWindowWheel (e) {
		const delta = e.deltaY > 0 ? 1 : -1;
		const path = getEventPath(e);

		if (/\.(?:toolbox|canvas|footer)-container[. ]/.test(path)) {
			e.preventDefault();
			e.stopPropagation();
		}

		if (e.deltaY == 0) return;
		if (!/\.canvas-container[. ]/.test(path)) return;
		if (Date.now() - penWheelInfo.time < 120) return;

		// without any buttons
		if (e.buttons == 0) {
			if (penMode == 0) {
				setPenSize(
					{isHighPrecision: globals.useHighPrecisionPenSize, delta: delta},
					{isHighPrecision: true});
			}
			else if (penMode == 1) {
				setPenSize(
					{isHighPrecision: true},
					{isHighPrecision: globals.useHighPrecisionPenSize, delta: delta});
			}
		}
		// with the primary button
		else if (e.buttons & 1) {
			conlog('wheel with primary button');
		}
		// with the secondary button
		else if (e.buttons & 2) {
			if (!busy) {
				setZoomFactor(Math.floor(zoomRatio * 10 + delta) / 10, true)
				.then(() => { updateHUD(`${zoomRatio.toFixed(1)}x`) });
			}
		}

		penWheelInfo.delta = delta;
		penWheelInfo.time = Date.now();
	}

	function handleContextMenu (e) {
		if (pointerInfo.captureId != null
		||  /\.canvas-container[. ]/.test(getEventPath(e))) {
			e.preventDefault();
			e.stopPropagation();
		}
	}

	function handleGenericInput (e) {
		const classList = e.target.classList;

		if (classList.contains('pen-size-range')) {
			setPenSize(e.target.valueAsNumber, {value: globals.eraserSize, isHighPrecision: true});
		}
		else if (classList.contains('eraser-size-range')) {
			setPenSize({value: globals.penSize, isHighPrecision: true}, e.target.valueAsNumber);
		}
		else if (classList.contains('interpolate-scale')) {
			setInterpolateScale(e.target.valueAsNumber);
		}
		else if (classList.contains('coord-upscale-factor')) {
			setCoordUpscaleFactor(e.target.valueAsNumber);
		}
		else if (classList.contains('closed-color-threshold-range')) {
			setClosedColorThreshold(e.target.valueAsNumber);
		}
		else if (classList.contains('overfill-size-range')) {
			setOverfillSize(e.target.valueAsNumber);
		}
		else {
			if (e.target.nodeName.toLowerCase() == 'input' && e.target.type.toLowerCase() == 'range') {
				for (let i = 0, p = e.target.nextElementSibling; i < 5 && p; i++, p = p.nextElementSibling) {
					if (p.classList.contains('output')) {
						p.textContent = e.target.valueAsNumber.toFixed(2);
						break;
					}
				}
			}
		}
	}

	function handleDebug (e) {
		switch (e.detail) {
		case 'plotPoints':
			console.log('points: ' + JSON.stringify(points, null, ''));
			break;

		case 'dumpPoints':
			{
				function f (value) {
					return ('        ' + value.toFixed(2)).substr(-8);
				}

				function r (value) {
					return ('                ' + value).substr(-16);
				}

				const buffer = [];
				buffer.push('       time             type | x native  y native   distance |        x         y   distance');
				buffer.push('----------- ---------------- | --------  -------- ---------- | --------  -------- ----------');

				const ctx = canvases.layer2.getContext('2d');
				initContext(ctx);
				ctx.save();
				ctx.strokeStyle = 'blue';
				ctx.lineWidth = 1;

				for (let i = 0; i < points.length; i++) {
					const [computedX, computedY, x, y, time, type] = points[i];
					if (i == 0) {
						buffer.push(`            ${r(type)} | ${f(computedX)}, ${f(computedY)} (${f(0)})`);
					}
					else {
						const [computedxPrev, computedyPrev, xPrev, yPrev, timePrev, typePrev] = points[i - 1];
						const elapsed = ('        ' + (time - timePrev)).substr(-8);
						const d1 = Math.sqrt(
							Math.pow(computedX - computedxPrev, 2) + Math.pow(computedY - computedyPrev, 2));
						const d2 = Math.sqrt(
							Math.pow(x - xPrev, 2) + Math.pow(y - yPrev, 2));

						let line =
							`+${elapsed}ms ${r(type)} | ` +
							`${f(x)}, ${f(y)} (${f(d2)}) | ` +
							`${f(computedX)}, ${f(computedY)} (${f(d1)})`;

						if (points[i][6]) {
							line += ` *** ${points[i][6]} ***`;
						}

						buffer.push(line);
					}

					if (i == 0 || i == points.length - 1 || points[i][6]) {
						ctx.beginPath();
						ctx.arc(points[i][0], points[i][1], 2, 0, 2 * Math.PI);
						ctx.stroke();
					}
				}

				buffer.push('----');
				buffer.push(`lineWidth: ${points.__momocanLineWidth__}`);
				buffer.push(`interpolateScale: ${points.__momocanInterpolateScale__}`);

				ctx.restore();
				console.log(buffer.join('\n'));
			}
			break;
		}
	}

	function handleDrawToolsHover (e) {
		const description = $qs('.draw-tools .draw-tools-text', container);
		$t(description, e.target.getAttribute('data-title'));
	}

	function handleDrawToolsLeave (e) {
		const description = $qs('.draw-tools .draw-tools-text', container);
		$t(description, 'Drawing tools');
	}

	function handleLayerHover (e) {
		if (touchEnabled) return;

		if (layerHoverResetTimer) {
			clearTimeout(layerHoverResetTimer);
			layerHoverResetTimer = null;
		}

		const TIMER_INTERVAL = 250;
		const input = $qs('input', e.target);
		layerHoverResetTimer = setTimeout(function hover (input) {
			layerHoverResetTimer = null;
			const elm = document.elementFromPoint(pointerInfo.clientX, pointerInfo.clientY);
			if (input == elm || input == $qs('input', elm)) {
				layerHoverResetTimer = setTimeout(hover, TIMER_INTERVAL, input);
			}
			else {
				for (let i = 0; i < 3; i++) {
					const canvas = canvases[`layer${i}`];
					canvas.style.opacity = '';
					canvas.style.outline = i > 0 && i == sessions.layerIndex ? LAYER_OUTLINE : '';
				}
			}
		}, TIMER_INTERVAL, input);

		const hoverIndex = input.getAttribute('value') - 0;
		for (let i = 0; i < 3; i++) {
			const canvas = canvases[`layer${i}`];
			if (i == hoverIndex) {
				canvas.style.opacity = '';
				canvas.style.outline = i > 0 ? LAYER_OUTLINE : '';
			}
			else {
				canvas.style.opacity = '0.01';
				canvas.style.outline = '';
			}
		}
	}

	// <<<2 entry point

	function start () {
		// ensure container element exists
		container = $(CONTAINER_ID) || insertMarkup();

		if (container.classList.contains('run')) return;
		if (container.classList.contains('running')) return;

		// initialize canvas pool
		canvases.hd = document.createElement('canvas');				// high-density canvas
		canvases.crispPen = initCrispPen();							// crisp edged pen canvas
		canvases.crispEraser = initCrispPen();						// crisp edged eraser canvas
		canvases.crispFlood = initCrispPen();						// crisp edged flood canvas
		canvases.cursor = $qs(':scope > canvas.cursor', container);	// cursor canvas
		canvases.layer0 = $qs('.canvas-wrap .canvas-0', container);	// layers
		canvases.layer1 = $qs('.canvas-wrap .canvas-1', container);
		canvases.layer2 = $qs('.canvas-wrap .canvas-2', container);
		canvases.layerPen = $qs('.canvas-wrap .canvas-pen', container);
		canvases.cursor.style.left = -canvases.cursor.width + 'px';
		canvases.cursor.style.top = -canvases.cursor.height + 'px';

		// register all native events
		event
			.add(document, 'touchstart', handleTouchstart)
			.add(document, 'touchmove', handleTouchmove, {passive: false})
			//.add(document, 'paste', handlePaste)

			.add(window, 'unload', handleWindowUnload)
			.add(window, 'resize', handleWindowResize)
			.add(window, 'focusin', handleWindowFocusin)
			.add(window, 'keydown', handleWindowKeydown, true)
			.add(window, 'keyup', handleWindowKeyup, true)

			.add(container, pointerEventNames.down, handlePointerDown)
			.add(container, pointerEventNames.move, handlePointerMove)
			.add(container, pointerEventNames.up, handlePointerUp)
			.add(container, 'contextmenu', handleContextMenu)
			.add(container, 'input', handleGenericInput)
			.add(container, 'momocan.debug', handleDebug)

			.add(container, 'wheel', handleWindowWheel);

		hoverWrappers.push(
			createHoverWrapper($qs('.draw-tools-wrap', container), 'a', handleDrawToolsHover, handleDrawToolsLeave),
			createHoverWrapper($qs('.layer', container), 'label', handleLayerHover));

		// register click-dispatched events
		clickDispatcher
			// palette options
			.add(['#fg-color', '#bg-color'], e => {
				startColorPicker(e.target);
			})
			.add('#reset', () => {
				sessions.foregroundColor = setColor($qs('.current-color [href="#fg-color"]', container), DEFAULT_SESSIONS.foregroundColor);
				sessions.backgroundColor = setColor($qs('.current-color [href="#bg-color"]', container), DEFAULT_SESSIONS.backgroundColor);
				updateCursor();
				updateCrispPenColor();
				updateCrispEraserColor();
				context = null;
			})
			.add('#swap', () => {
				const tmp = sessions.foregroundColor;
				sessions.foregroundColor = setColor($qs('.current-color [href="#fg-color"]', container), sessions.backgroundColor);
				sessions.backgroundColor = setColor($qs('.current-color [href="#bg-color"]', container), tmp);
				updateCursor();
				updateCrispPenColor();
				updateCrispEraserColor();
				context = null;
			})
			.add('#palette', e => {
				const palette = e.target.getAttribute('data-palette');
				sessions.foregroundColor = setColor($qs('.current-color [href="#fg-color"]', container), palette);
				updateCursor();
				updateCrispPenColor();
				context = null;
			})

			// draw method options
			.add('#draw-method', e => {
				let t = e.target;
				if (t.parentElement.nodeName.toLowerCase() == 'a') {
					t = t.parentElement;
				}

				const index = t.getAttribute('data-index') - 0;

				setPenMode(index);
				setPenSize(globals.penSize, globals.eraserSize, true);
			})
			.add(['#decrement', '#increment'], e => {
				const anchor = e.target.parentNode;
				if (!anchor || anchor.nodeName.toLowerCase() != 'a') return;

				const isIncrement = anchor.getAttribute('href') == '#increment';
				const sign = isIncrement ? 1 : -1;
				const target = isIncrement ? anchor.previousElementSibling : anchor.nextElementSibling;
				if (!target) return;

				switch (target.className) {
				case 'pen-size-range':
					setPenSize(
						{isHighPrecision: !!(e.ctrlKey ^ globals.useHighPrecisionPenSize), delta: 1 * sign},
						{isHighPrecision: true});
					break;

				case 'eraser-size-range':
					setPenSize(
						{isHighPrecision: true},
						{isHighPrecision: !!(e.ctrlKey ^ globals.useHighPrecisionPenSize), delta: 1 * sign});
					break;

				case 'closed-color-threshold-range':
					setClosedColorThreshold(globals.closedColorThreshold + 0.1 * sign);
					break;

				case 'overfill-size-range':
					setOverfillSize(globals.overfillSize + 0.1 * sign);
					break;
				}
			})
			.add('#enable-pen-adjustment', e => {
				setEnablePenAdjustment(e.target.checked);
			})
			.add('#emulate-pointed-end', e => {
				setEmulatePointedEnd(e.target.checked);
			})
			.add('#blurred-eraser', e => {
				setBlurredEraser(e.target.checked);
			})
			.add('#sample-merged', e => {
				setSampleMerged(e.target.checked);
			})
			.add('#draw-method-options-more', e => {
				const optionsContainer = $qs('.draw-method-options[data-target-method]:not(.hide) .secondary', container);
				if (!optionsContainer) return;
				startDrawMethodOption(e.target, optionsContainer);
			})

			// zoom options
			.add('#zoom-factor', e => {
				setZoomFactor(e.target.value - 0);
			})

			// layer options
			.add('#layer-index', e => {
				setLayerIndex(e.target.value - 0);
			})

			// draw tools
			.add('#draw-undo', () => {
				!busy && undo();
			})
			.add('#draw-redo', () => {
				!busy && undo(true);
			})
			.add('#draw-tool', e => {
				if (busy) return;

				let type, p = e.target;

				for (; p; p = p.parentNode) {
					type = p.getAttribute('data-type');
					if (type) break;
				}
				if (!type) return;

				if (type in drawTools) {
					enterBusy('draw tool');
					Promise.resolve(drawTools[type](p))
					.then(() => {pushUndo()})
					.finally(() => {leaveBusy('draw tool')});
				}
			})
			.add('#draw-settings-open', () => {
				startSettingsDialog();
			})

			// settings
			.add('#full-reset-palettes', () => {
				initPalettes(DEFAULT_GLOBALS.palettes);
			})
			.add('#use-pixelated-lines', e => {
				setUsePixelatedLines(e.target.checked);
			})
			.add('#use-cross-cursor', e => {
				setUseCrossCursor(e.target.checked);
			})
			.add('#use-high-precision-pen-size', e => {
				setUseHighPrecisionPenSize(e.target.checked);
			})

			// footer buttons
			.add('#draw-complete', () => {
				try {
					opts.onok && opts.onok(mergeLayers());
				}
				finally {
					leave();
				}
			})
			.add('#draw-cancel', () => {
				try {
					opts.oncancel && opts.oncancel();
				}
				finally {
					leave();
				}
			});

		// register longpress-dispatched events
		longPressDispatcher
			.add('#palette', e => {
				startColorPicker(e.target);
			})
			.add('#draw-tool', e => {
				if (busy) return;

				let type, p = e.target;

				for (; p; p = p.parentNode) {
					type = p.getAttribute('data-type');
					if (type) break;
				}
				if (!type) return;

				type += '_longpress';
				if (type in drawTools) {
					enterBusy('draw tool (longpress)');
					Promise.resolve(drawTools[type](p))
					.then(() => {pushUndo()})
					.finally(() => {
						leaveBusy('draw tool (longpress)');
					});
				}
			});

		// load persistent data. this may be asynchronous
		return restorePersistents()
			.then(() => {
				// show whole container, but because opacity is zero at this point,
				// so container is actualy invisible.
				container.classList.remove('hide');
			})
			.then(() => {
				// store first state of canvas into undo buffer
				pushUndo();

				// this call is not affected by transition so it completes immediately.
				setZoomFactor(sessions.zoomFactor);

				// align the dimension of all options of draw methods
				let maxWidth = 0;
				let maxHeight = 0;
				$qsa('.draw-method-options', container).forEach(node => {
					const hidden = node.classList.contains('hide');
					node.classList.remove('hide');
					maxWidth = Math.max(maxWidth, node.offsetWidth);
					maxHeight = Math.max(maxHeight, node.offsetHeight);
					hidden && node.classList.add('hide');
				});
				// give a little extra size
				maxWidth += 2;
				maxHeight += 2;
				$qs('.draw-method-options-wrap').style.width = `${maxWidth}px`;
				$qsa('.draw-method-options', container).forEach(node => {
					//node.style.width = `${maxWidth}px`;
					node.style.height = `${maxHeight}px`;
				});

				plotPoints();

				// start opacity transition
				container.classList.add('run');
				return transitionendp(container);
			})
			.then(() => {
				container.classList.add('running');
				opts.onstart && opts.onstart();
			})
			.catch(err => {
				console.error(err.stack);
			});
	}

	function leave () {
		// remove all native events
		event.removeAll();
		while (hoverWrappers.length) {
			hoverWrappers.pop().dispose();
		}

		// remove click-dispatched events
		clickDispatcher.removeAll();
		longPressDispatcher.removeAll();

		// save canvas image
		exportCanvas();

		// save global data
		window.localStorage.setItem(GLOBAL_PERSIST_KEY, JSON.stringify(globals));

		// save session data
		sessions.canvasInitialized = true;
		container.setAttribute(SESSION_PERSIST_KEY, JSON.stringify(sessions));

		// start opacity transition
		container.classList.remove('running');
		container.classList.remove('run');
		return transitionendp(container).then(() => {
			container.classList.add('hide');
			opts.onclose && opts.onclose();
			container = opts = canvases = null;
		});
	}

	function insertMarkup () {
		let markup = `
<div id="${CONTAINER_ID}" class="hide">
<div class="toolbox-container"><div class="toolbox-container-inner">
	<div class="toolbox">
		<div>
			<div class="palette-wrap">
				<div class="current-color"><a href="#bg-color"></a><a href="#fg-color"></a></div>
				<div class="sub-colors-wrap">
					<div class="sub-colors"
						><a href="#palette" data-index="0"></a
						><a href="#palette" data-index="1"></a
						><a href="#palette" data-index="2"></a
						><a href="#palette" data-index="3"></a
						><a href="#palette" data-index="4"></a
					></div>
					<div class="sub-colors"
						><a href="#palette" data-index="5"></a
						><a href="#palette" data-index="6"></a
						><a href="#palette" data-index="7"></a
						><a href="#palette" data-index="8"></a
						><a href="#palette" data-index="9"></a
					></div>
				</div>
			</div>
			<div class="right">
				<a href="#reset" title="Default foreground and background colors (D)">Reset</a>
				<a href="#swap" title="Swap foreground and background colors (X)">Swap</a>
			</div>
		</div>
		<div>
			<div><span class="draw-method-text">Pen</span><span class="draw-subtext"></span></div>
			<div class="draw-method-wrap">
				<div class="draw-method-list-wrap">
					<a href="#draw-method" data-key="pen" data-index="0" title="Pen (P)"><img src="${MOMO_URL}/pen.svg"></a>
					<a href="#draw-method" data-key="eraser" data-index="1" title="Eraser (E)"><img src="${MOMO_URL}/erase.svg"></a>
					<a href="#draw-method" data-key="bucket" data-index="2" title="Paint bucket (G)"><img src="${MOMO_URL}/paint-bucket.svg"></a>

					<a href="#draw-method" data-key="select" data-index="3" title="Rectangular marquee tool (M)"><img src="${MOMO_URL}/select.svg"></a>
					<a href="#draw-method" data-key="lasso" data-index="4" title="Lasso (L)"><img src="${MOMO_URL}/lasso.svg"></a>
					<a href="#draw-method" data-key="move" data-index="5" title="Move (V)"><img src="${MOMO_URL}/move.svg"></a>
				</div>
				<div class="draw-method-options-wrap">
					<div class="draw-method-options" data-target-method="pen">
						<div class="primary">
							<div class="main">
								<div class="head">Size:</div>
								<a class="incdec" href="#decrement" title="Thinner (Ctrl+click for fine adjustment)"><img src="${MOMO_URL}/minus.svg"></a><input class="pen-size-range" type="range" min="1" max="24" step="1"><a class="incdec" href="#increment" title="Thicker (Ctrl+click for fine adjustment)"><img src="${MOMO_URL}/plus.svg"></a>
								<canvas class="pen-size-canvas" width="24" height="24"></canvas>
							</div>
						</div>
						<div class="secondary hide">
							<div class="right">
								<label title="Line correction and curve interpolation (A)"><input class="enable-pen-adjustment" type="checkbox" data-href="#enable-pen-adjustment">Correction and interpolation</label>
								<label title="Make the ends of lines thinner (B)"><input class="emulate-pointed-end" type="checkbox" data-href="#emulate-pointed-end">Thin tail</label>
							</div>
						</div>
					</div>
					<div class="draw-method-options" data-target-method="eraser">
						<div class="primary">
							<div class="main">
								<div class="head">Size:</div>
								<a class="incdec" href="#decrement"><img src="${MOMO_URL}/minus.svg"></a><input class="eraser-size-range" type="range" min="1" max="24" step="1"><a class="incdec" href="#increment"><img src="${MOMO_URL}/plus.svg"></a>
								<canvas class="eraser-size-canvas" width="24" height="24"></canvas>
							</div>
						</div>
						<div class="secondary hide">
							<div class="right">
								<label><input class="blurred-eraser" type="checkbox" data-href="#blurred-eraser">Blur edges</label>
							</div>
						</div>
					</div>
					<div class="draw-method-options" data-target-method="bucket">
						<div class="primary">
							<div class="main">
								<label><input class="sample-merged" type="checkbox" data-href="#sample-merged">All layers</label>
							</div>
						</div>
						<div class="secondary hide">
							<div class="main">
								<div class="head">Paint area tolerance:</div>
								<a class="incdec" href="#decrement"><img src="${MOMO_URL}/minus.svg"></a><input class="closed-color-threshold-range" type="range" min="0" max="0.5" step="any"><a class="incdec" href="#increment"><img src="${MOMO_URL}/plus.svg"></a><span class="output closed-color-threshold-text">0.50</span>
								<div class="head">Paint area overfill:</div>
								<a class="incdec" href="#decrement"><img src="${MOMO_URL}/minus.svg"></a><input class="overfill-size-range" type="range" min="0" max="10" step="0.1"><a class="incdec" href="#increment"><img src="${MOMO_URL}/plus.svg"></a><span class="output overfill-size-text">0</span>
							</div>
						</div>
					</div>
					<div class="draw-method-options" data-target-method="select">Un</div>
					<div class="draw-method-options" data-target-method="lasso">Uun</div>
					<div class="draw-method-options" data-target-method="move">Uuun</div>
					<a class="draw-method-options-more" href="#draw-method-options-more" title="More options...">...</a>
				</div>
			</div>
		</div>
		<div>
			<div><span class="draw-zoom-text">Zoom</span><span class="draw-subtext"></span></div>
			<div>
				<label title="1x (Ctrl+1)"><input type="radio" name="draw-zoom" value="1" data-href="#zoom-factor">1x</label>
				<label title="2x (Ctrl+2)"><input type="radio" name="draw-zoom" value="2" data-href="#zoom-factor">2x</label>
			</div>
			<div>
				<label title="3x (Ctrl+3)"><input type="radio" name="draw-zoom" value="3" data-href="#zoom-factor">3x</label>
				<label title="4x (Ctrl+4)"><input type="radio" name="draw-zoom" value="4" data-href="#zoom-factor">4x</label>
			</div>
			<div>
				<label title="Fit screen (Ctrl+0)"><input type="radio" name="draw-zoom" value="0" data-href="#zoom-factor">Fit screen</label>
			</div>
		</div>
		<div class="layer">
			<div>Layers</div>
			<div>
				<label title="Background"><input type="radio" name="draw-layer" value="0" data-href="#layer-index">BG</label>
			</div>
			<div>
				<label title="Middle ground"><input type="radio" name="draw-layer" value="1" data-href="#layer-index">Mid</label>
				<label title="Foreground"><input type="radio" name="draw-layer" value="2" data-href="#layer-index">FG</label>
			</div>
			<div class="right">
				<a href="#draw-tool" data-type="paint_layer" title="Fills the current layer with the foreground color">Fill</a>
				<a href="#draw-tool" data-type="clear_layer" title="Clears the current layer">Erase</a>
			</div>
		</div>
		<div class="draw-tools">
			<div><span class="draw-tools-text">Drawing tools</span></div>
			<div class="draw-tools-wrap">
				<div>
					<a href="#draw-undo" data-title="Undo (Ctrl+Z)"><img src="${MOMO_URL}/undo.svg"></a>
					<a href="#draw-redo" data-title="Redo (Ctrl+Y)"><img class="flipx" src="${MOMO_URL}/undo.svg"></a>
					<a href="#draw-tool" data-type="rotate_left" data-title="Rotate canvas left"><img src="${MOMO_URL}/rotate-left.svg"></a>
					<a href="#draw-tool" data-type="rotate_right" data-title="Rotate canvas right"><img class="flipx" src="${MOMO_URL}/rotate-left.svg"></a>
					<a href="#draw-settings-open" data-title="Settings..."><img src="${MOMO_URL}/settings-gears.svg"></a>

					<a href="#draw-tool" data-type="flip_horizontally" data-title="Flip horizontally"><img src="${MOMO_URL}/flip-horizontally.svg"></a>
					<a href="#draw-tool" data-type="flip_vertically" data-title="Flip vertically"><img src="${MOMO_URL}/flip-vertically.svg"></a>
					<a href="#draw-tool" data-type="merge_layers" data-title="Merge all layers to background" class="multi"><img src="${MOMO_URL}/merge.svg"></a>
					<a href="#draw-tool" data-type="resize" data-title="Canvas size..." class="multi"><img src="${MOMO_URL}/resize.svg"></a>
				</div>
				<div>
					<a href="#draw-tool" data-type="init_canvas" data-title="Initialize canvas" class="multi"><img src="${MOMO_URL}/momo.svg"></a>
				</div>
			</div>
		</div>
	</div>
	<div class="tips">
		<span class="normal">
			<span class="key">LMB+RMB</span>Straight line
			<span class="key">Ctrl</span>Eyedropper
			<span class="key">Shift</span>Pen↔Eraser
			<span class="key">Space</span>Scroll
			<span class="keys"><span class="key">[</span><span class="key">]</span></span>Pen size adjustment
			<span class="key">^Z</span>Undo
			<span class="key">^Y</span>Redo
		</span>
		<span class="right-click hide">
			<span class="key">Wheel</span>Zoom
			<span class="keys"><span class="key">↶</span><span class="key">↷</span></span>Rotate
		</span>
	</div>
</div></div>
<div class="canvas-container">
	<div class="canvas-wrap">
		<div class="canvas-wrap-inner">
			<canvas class="canvas-0"></canvas>
			<canvas class="canvas-1"></canvas>
			<canvas class="canvas-2"></canvas>
			<canvas class="canvas-pen"></canvas>
		</div>
	</div>
</div>
<div class="footer-container"><div class="footer-container-inner">
	<div>
		<span id="momocan-status"></span>
		<button data-href="#draw-complete">Finish drawing</button>
	</div>
	<div>
		<button data-href="#draw-cancel">Cancel</button>
		<span id="momocan-credit"><a class="credit" href="https://appsweets.net/momo/" target="_blank" draggable="false">桃の缶詰/${VERSION}</a></span>
	</div>
</div></div>
<div class="settings-container hide">
	<div class="settings-wrap">
		<div class="settings-head">Settings</div>

		<div class="settings-head2">Cursor</div>
		<div class="settings-item">
			<label><input class="use-cross-cursor" type="checkbox" data-href="#use-cross-cursor">Add a cross pattern</label>
		</div>

		<div class="settings-head2">Palette</div>
		<div class="settings-item">
			<div>Rest</div>
			<button data-href="#full-reset-palettes">Reset entire palette</button>
		</div>

		<div class="settings-head2">Pen</div>
		<div class="settings-item">
			<div>Coordinate correction upscale factor</div>
			1.0<input class="coord-upscale-factor" type="range" min="1" max="4" step="any">4.0<span class="output coord-upscale-text"></span>
			<div>Set the internal upscaling factor to correct the coordinate system of the pointing device.
			The higher the magnification, the more that line blurring will be surpressed, but it will result in poorer tracking.
			A setting of around 3.4 will be roughly the same as the skin-coloured canvas. A setting of 1.0 will disable the correction.</div>
		</div>
		<div class="settings-item">
			<div>Spline interpolation strength</div>
			0<input class="interpolate-scale" type="range" min="0" max="1" step="any">1.0<span class="output interpolate-text"></span>
			<div>Specifies the degree to which the lines are smoothed. The higher the strength, the smoother the lines will be, but fine details will be lost.</div>
		</div>
		<div class="settings-item">
			<div>Pen drawing</div>
			<label><input class="use-pixelated-lines" type="checkbox" data-href="#use-pixelated-lines">Draw along pixel boundaries</label>
			<label><input class="use-high-precision-pen-size" type="checkbox" data-href="#use-high-precision-pen-size">Always change pen size in 1/3 pixel increments</label>
		</div>
	</div>
	<div class="settings-footer">
			<button data-href="#draw-settings-close">Close</button>
	</div>
</div>
<canvas class="cursor"></canvas>
</div>
		`;

		if (typeof opts.onmarkup == 'function') {
			markup = opts.onmarkup(markup);
		}

		document.body.insertAdjacentHTML('beforeend', markup);
		return $(CONTAINER_ID);
	}

	return {
		start: start
	};
}

/*
 * <<<1 bootstrap
 */

(function () {
	const CONTAINER_ID = 'momocan-container';
	const START_LINK_ID = 'momocan-start-link';
	const THUMBNAIL_ID = 'momocan-thumbnail';
	const IMAGEDATAURL_ID = 'baseform';

	function loadStyle (url) {
		return new Promise((resolve, reject) => {
			const link = document.head.appendChild(document.createElement('link'));
			link.rel = 'stylesheet';
			link.type = 'text/css';
			link.href = url || `${MOMO_URL}/can.css`;

			if ('onload' in link) {
				link.onload = resolve;
			}
			else {
				setTimeout(resolve, 1000 * 3);
			}

			if ('onerror' in link) {
				link.onerror = reject;
			}
		});
	}

	function installInto2chan () {
		const oebtnd = $('oebtnd');
		const div = oebtnd.parentNode.insertBefore(document.createElement('div'), oebtnd.nextSibling);
		const anchor = div.appendChild(document.createElement('a'));
		anchor.id = START_LINK_ID;
		anchor.href = '#start-momocan';
		anchor.textContent = 'Tegaki (momo)';
		anchor.style.fontSize = 'x-small';
		anchor.addEventListener('click', e => {
			e.preventDefault();
			let momocan = createMomocan({
				onok: canvas => {
					const dataURL = canvas.toDataURL();

					const baseform = $(IMAGEDATAURL_ID);
					if (baseform) {
						baseform.setAttribute('value', dataURL.replace(/^[^,]+,/, ''));
					}

					const oejs = $('oejs');
					if (oejs) {
						const image = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
						oejs.width = canvas.width;
						oejs.height = canvas.height;
						oejs.getContext('2d').putImageData(image, 0, 0);
					}

					let thumb = $(THUMBNAIL_ID);
					if (!thumb) {
						const anchor = $(START_LINK_ID);
						const div = anchor.appendChild(document.createElement('div'));
						thumb = div.appendChild(document.createElement('img'));
						thumb.id = THUMBNAIL_ID;
						thumb.style.width = '100%';
					}
					thumb.src = dataURL;
				},
				oncancel: () => {
					const baseform = $(IMAGEDATAURL_ID);
					if (baseform) {
						baseform.removeAttribute('value');
					}

					const thumb = $(THUMBNAIL_ID);
					if (thumb) {
						const parent = thumb.parentNode;
						parent.parentNode.removeChild(parent);
					}
				},
				onclose: () => {
					momocan = null;
					delete window.Akahuku.momocan;
				}
			});

			momocan.start();
		});

		anchor.click();
	}

	function installIntoKoko() {
		const comlbl = $qs('label[for=com]');
		const div = comlbl.parentNode.insertBefore(document.createElement('div'), comlbl.nextSibling);
		const anchor = div.appendChild(document.createElement('a'));
		anchor.id = START_LINK_ID;
		anchor.href = '#start-momocan';
		anchor.textContent = 'Tegaki (momo)';
		anchor.style.fontSize = 'x-small';
		anchor.addEventListener('click', e => {
			e.preventDefault();
			let momocan = createMomocan({
				onok: canvas => {
					const dataURL = canvas.toDataURL();

					const upfile = $('upfile');
					if (upfile) {
						canvas.toBlob(blob => {
							const dt = new DataTransfer();
							const timestamp = new Date().getTime();
							const filename = `tegaki${timestamp}.png`;
							const file = new File([blob], filename, {type: blob.type});
							dt.items.add(file);
							upfile.files = dt.files;
						});
					}

					let thumb = $(THUMBNAIL_ID);
					if (!thumb) {
						const anchor = $(START_LINK_ID);
						const div = anchor.appendChild(document.createElement('div'));
						thumb = div.appendChild(document.createElement('img'));
						thumb.id = THUMBNAIL_ID;
						thumb.style.width = '100%';
					}
					thumb.src = dataURL;
				},
				oncancel: () => {
					const thumb = $(THUMBNAIL_ID);
					if (thumb) {
						const parent = thumb.parentNode;
						parent.parentNode.removeChild(parent);
					}
					const upfile = $('upfile');
					if (upfile) {
						upfile.value = '';
					}
				},
				onclose: () => {
					momocan = null;
					delete window.Akahuku.momocan;
				}
			});

			momocan.start();
		});
	}

	function initController () {
		if (window.Akahuku == undefined) {
			window.Akahuku = {};
		}
		window.Akahuku.momocan = Object.seal({
			plotPoints: () => {
				return $(CONTAINER_ID).dispatchEvent(new CustomEvent('momocan.debug', {detail: 'plotPoints'}));
			},
			dumpPoints: () => {
				return $(CONTAINER_ID).dispatchEvent(new CustomEvent('momocan.debug', {detail: 'dumpPoints'}));
			}
		});
		window.Akahuku.storage = {
			config: {
				tegaki_max_width: {
					type:'int',
					value:400,
					name:'Maximum width of tegaki canvas',
					min:16,max:400
				},
				tegaki_max_height: {
					type:'int',
					value:400,
					name:'Maximum height of tegaki canvas',
					min:16,max:400
				}
			}
		};
	}

	function initControllerOnExtension () {
		window.Akahuku.momocan = Object.seal({
			create: createMomocan,
			loadStyle: loadStyle
		});
		// window.Akahuku.storage is defined by akahukuplus.js
	}

	$qsa('script[src*="appsweets.net/momo/"]').forEach(s => {
		s.parentNode.removeChild(s);
	});

	// if (/^https?:\/\/img\.heyuri\.net\//.test(window.location.href)) {
	if ($qs('label[for=com]')) {
		const comlbl = $qs('label[for=com]');
		if (!comlbl) return;

		const container = $('momocan-container');
		if (container && container.classList.contains('run')) return;

		const anchor = $(START_LINK_ID);
		if (anchor) {
			anchor.click();
		}
		else {
			loadStyle().then(() => {
				initController();
				installIntoKoko();
			});
		}
	}
	else if (/^https?:\/\/[^.]+\.2chan\.net\//.test(window.location.href)) {
		// inside content script of the akahukuplus extension
		if (window.chrome && chrome.runtime && chrome.runtime.id && window.Akahuku) {
			initControllerOnExtension();
		}

		// running on a normal web page
		else {
			const oebtnd = $('oebtnd');
			if (!oebtnd) return;

			const container = $('momocan-container');
			if (container && container.classList.contains('run')) return;

			const anchor = $(START_LINK_ID);
			if (anchor) {
				anchor.click();
			}
			else {
				loadStyle().then(() => {
					initController();
					installInto2chan();
				});
			}
		}
	}
	else if (/^https?:\/\/(?:[^.]+\.)?appsweets\.net\/momo\//.test(window.location.href)) {
		loadStyle().then(() => {
			initController();
			$(START_LINK_ID).addEventListener('click', e => {
				e.preventDefault();
				let momocan = createMomocan({
					onmarkup: markup => {
						conlog('onmarkup');
						return markup;
					},
					onstart: () => {
						conlog('onstart');
					},
					onok: canvas => {
						$('momocan-result').src = canvas.toDataURL();
					},
					oncancel: () => {
						console.log('oncancel');
					},
					onclose: () => {
						console.log('onclose');
						momocan = null;
					}
				});

				momocan.start();
			});

			if (/#autostart\b/.test(location.hash)) {
				if (/^(?:complete|interactive)$/.test(document.readyState)) {
					$(START_LINK_ID).click();
				}
				else {
					document.addEventListener('load', e => {
						$(START_LINK_ID).click();
					});
				}
			}
		});
	}
})();

})(this);

// vim:set ts=4 sw=4 fenc=UTF-8 ff=unix ft=javascript fdm=marker fmr=<<<,>>> :

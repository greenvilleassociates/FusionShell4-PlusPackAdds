kampfer.require('events');
kampfer.require('Class');

kampfer.provide('events.EventTarget');

/*
 * 所有需要实现自定义事件的类都必须继承EventTarget类。
 */

kampfer.events.EventTarget = kampfer.Class.extend({
	
	_parentNode : null,
	
	addListener : function(type, listener, context) {
		k.events.addListener(this, type, listener, context);
	},
	
	removeListener : function(type, listener) {
		k.events.removeListener(this, type, listener);
	},
	
	dispatch : function(type) {
		if(type) {
			var args = Array.prototype.slice.apply(arguments);
			args.unshift(this);
			k.events.dispatch.apply(null, args);
		}
	},
	
	getParentEventTarget : function() {
		return this._parentNode;
	},
	
	setParentEventTarget : function(obj) {
		this._parentNode = obj;
	},
	
	dispose : function() {
		this._parentNode = null;
		k.events.removeListener(this);
	}
	
});
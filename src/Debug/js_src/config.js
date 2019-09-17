import $ from "jquery";

import {lsGet,lsSet} from "./http.js";

export function Config(defaults, localStorageKey) {
    var storedConfig = null;
    if (defaults.useLocalStorage) {
        storedConfig = lsGet(localStorageKey);
    }
    this.config = $.extend({}, defaults, storedConfig || {});
    // console.warn('config', JSON.parse(JSON.stringify(this.config)));
    this.haveSavedConfig = typeof storedConfig === "object";
    this.localStorageKey = localStorageKey;
    this.localStorageKeys = ["persistDrawer","openDrawer","openSidebar","height","linkFiles","linkFilesTemplate"];
}

Config.prototype.get = function(key) {
    if (typeof key == "undefined") {
        return JSON.parse(JSON.stringify(this.config));
    }
    return typeof(this.config[key]) !== "undefined"
        ? this.config[key]
        : null;
}

Config.prototype.set = function(key, val) {
    var lsObj = {},
        setVals = {},
        haveLsKey = false;
    if (typeof key == "object") {
        setVals = key;
    } else {
        setVals[key] = val;
    }
    // console.log('config.set', setVals);
    for (var k in setVals) {
        this.config[k] = setVals[k];
    }
    if (this.config.useLocalStorage) {
        lsObj = lsGet(this.localStorageKey) || {};
        if (setVals.linkFilesTemplateDefault && !lsObj.linkFilesTemplate) {
            // we don't have a user specified template... use the default
            this.config.linkFiles = setVals.linkFiles = true;
            this.config.linkFilesTemplate = setVals.linkFilesTemplate = setVals.linkFilesTemplateDefault;
        }
        for (var i = 0, count = this.localStorageKeys.length; i < count; i++) {
            key = this.localStorageKeys[i];
            if (typeof setVals[key] !== "undefined") {
                haveLsKey = true;
                lsObj[key] = setVals[key];
            }
        }
        if (haveLsKey) {
            lsSet(this.localStorageKey, lsObj);
        }
    }
    this.haveSavedConfig = true;
}

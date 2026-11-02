import { defineStore } from 'pinia';

const SIDEBAR_KEY = 'pos.sidebarHidden';
const TEXT_KEY = 'pos.textSize';

export const textSizes = { small: 'Small', normal: 'Normal', large: 'Large' };

// Browser storage can be blocked (private windows), so every read and write is protected
function read(key) {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

function write(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Not saved, the choice just lasts until the page is closed
    }
}

const savedText = read(TEXT_KEY);

export const useUiStore = defineStore('ui', {
    state: () => ({
        sidebarHidden: read(SIDEBAR_KEY) === '1',
        textSize: savedText && savedText in textSizes ? savedText : 'normal',
    }),

    actions: {
        toggleSidebar() {
            this.sidebarHidden = !this.sidebarHidden;
            write(SIDEBAR_KEY, this.sidebarHidden ? '1' : '0');
        },

        setTextSize(size) {
            if (!(size in textSizes)) return;
            this.textSize = size;
            write(TEXT_KEY, size);
            this.applyTextSize();
        },

        // The CSS reads this attribute to pick the page's base font size
        applyTextSize() {
            document.documentElement.dataset.text = this.textSize;
        },
    },
});
/* ============================================================================
   Baustein.js — the client runtime.
   ----------------------------------------------------------------------------
   The whole browser half of the framework. It does three things:

     1. BINDS      every element carrying an event attribute, and re-binds
                   anything that arrives later (a MutationObserver watches the
                   document, so markup returned by a handler is live at once).
     2. SENDS      the element's attributes — plus those of its enclosing
                   component — to updater.php as JSON, with the CSRF token.
     3. APPLIES    the actions the server sends back, patching the DOM rather
                   than replacing it so focus, scroll and editors survive.

   It has no knowledge of any particular component; that lives in ui.js.
   Nothing here needs configuring, and it self-initialises at the bottom.
============================================================================ */

const Baustein = {
    /**
     * Pending debounce timer PER ELEMENT.
     *
     * This was a single shared timer, so typing in one field cancelled the
     * in-flight update of any other — two search boxes on one screen could not
     * both work, and the loser failed silently.
     */
    _timers: new WeakMap(),
    currentTarget: null,
    _confirmModal: null,
    _confirmYesBtn: null,
    _confirmNoBtn: null,
    _confirmTextNode: null,
    _confirmOnYes: null,
    _confirmOnNo: null,
    _serverModal: null,
    _serverCloseBtn: null,
    _serverBodyNode: null,

    confirm: function(message, onYes, onNo) {
        const ensure = () => {
            if (this._confirmModal) return;

            const overlay = document.createElement('div');
            overlay.id = 'x-confirm-overlay';
            overlay.className = 'modal-overlay';

            const box = document.createElement('div');
            box.className = 'modal';
            box.style.maxWidth = '420px';

            const msg = document.createElement('div');
            msg.className = 'modal-body text-sm';
            box.appendChild(msg);

            const actions = document.createElement('div');
            actions.className = 'modal-footer';

            const noBtn = document.createElement('button');
            noBtn.type = 'button';
            noBtn.className = 'btn';
            noBtn.textContent = 'No';

            const yesBtn = document.createElement('button');
            yesBtn.type = 'button';
            yesBtn.className = 'btn btn-primary';
            yesBtn.textContent = 'Yes';

            actions.appendChild(noBtn);
            actions.appendChild(yesBtn);
            box.appendChild(actions);
            overlay.appendChild(box);

            noBtn.addEventListener('click', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                const cb = this._confirmOnNo;
                this.closeConfirm();
                if (typeof cb === 'function') cb();
            }, true);

            yesBtn.addEventListener('click', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                const cb = this._confirmOnYes;
                this.closeConfirm();
                if (typeof cb === 'function') cb();
            }, true);

            this._confirmModal = overlay;
            this._confirmYesBtn = yesBtn;
            this._confirmNoBtn = noBtn;
            this._confirmTextNode = msg;
        };

        ensure();
        this._confirmOnYes = onYes;
        this._confirmOnNo = onNo;
        if (this._confirmTextNode) this._confirmTextNode.textContent = String(message ?? 'Are you sure?');

        if (this._confirmModal && !this._confirmModal.isConnected) {
            document.body.appendChild(this._confirmModal);
        }
    },

    closeConfirm: function() {
        this._confirmOnYes = null;
        this._confirmOnNo = null;
        if (this._confirmModal && this._confirmModal.parentNode) {
            this._confirmModal.parentNode.removeChild(this._confirmModal);
        }
    },

    /**
     * A message with a single button.
     *
     * Something a person should read had only two ways to reach the screen: the
     * Yes/No confirm, which asks a question nobody was asking, and
     * showServerResponse, which is a developer's payload dump titled "Server
     * response". A refusal like "this group still has tasks" is neither.
     */
    alert: function(message, onClose) {
        const ensure = () => {
            if (this._alertModal) return;

            const overlay = document.createElement('div');
            overlay.id = 'x-alert-overlay';
            overlay.className = 'modal-overlay';

            const box = document.createElement('div');
            box.className = 'modal';
            box.style.maxWidth = '420px';

            const msg = document.createElement('div');
            msg.className = 'modal-body text-sm';
            box.appendChild(msg);

            const actions = document.createElement('div');
            actions.className = 'modal-footer';

            const okBtn = document.createElement('button');
            okBtn.type = 'button';
            okBtn.className = 'btn btn-primary';
            okBtn.textContent = 'OK';
            actions.appendChild(okBtn);
            box.appendChild(actions);
            overlay.appendChild(box);

            okBtn.addEventListener('click', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                const cb = this._alertOnClose;
                this.closeAlert();
                if (typeof cb === 'function') cb();
            }, true);

            // Clicking the backdrop dismisses it; clicking the box does not.
            overlay.addEventListener('click', (ev) => {
                if (ev.target !== overlay) return;
                this.closeAlert();
            }, true);

            this._alertModal = overlay;
            this._alertTextNode = msg;
            this._alertOkBtn = okBtn;
        };

        ensure();
        this._alertOnClose = onClose;
        if (this._alertTextNode) this._alertTextNode.textContent = String(message ?? '');

        if (this._alertModal && !this._alertModal.isConnected) {
            document.body.appendChild(this._alertModal);
        }
        if (this._alertOkBtn) this._alertOkBtn.focus();
    },

    closeAlert: function() {
        this._alertOnClose = null;
        if (this._alertModal && this._alertModal.parentNode) {
            this._alertModal.parentNode.removeChild(this._alertModal);
        }
    },

    showServerResponse: function(payload, asHtml = false) {
        const ensure = () => {
            if (this._serverModal) return;

            const overlay = document.createElement('div');
            overlay.id = 'x-server-response-overlay';
            overlay.className = 'modal-overlay';

            const box = document.createElement('div');
            box.className = 'modal';
            box.style.maxWidth = '980px';

            const header = document.createElement('div');
            header.className = 'modal-header';

            const title = document.createElement('div');
            title.className = 'modal-title text-sm';
            title.textContent = 'Server response';

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn btn-sm';
            closeBtn.textContent = 'Close';

            header.appendChild(title);
            header.appendChild(closeBtn);

            const body = document.createElement('div');
            body.className = 'modal-body text-sm';

            box.appendChild(header);
            box.appendChild(body);
            overlay.appendChild(box);

            closeBtn.addEventListener('click', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                this.closeServerResponse();
            }, true);

            overlay.addEventListener('click', (ev) => {
                if (ev.target === overlay) this.closeServerResponse();
            }, true);

            this._serverModal = overlay;
            this._serverCloseBtn = closeBtn;
            this._serverBodyNode = body;
        };

        ensure();
        if (this._serverBodyNode) {
            if (asHtml) {
                this._serverBodyNode.innerHTML = String(payload ?? '');
            } else {
                const pre = document.createElement('pre');
                pre.style.whiteSpace = 'pre-wrap';
                pre.style.wordBreak = 'break-word';
                pre.textContent = String(payload ?? '');
                this._serverBodyNode.innerHTML = '';
                this._serverBodyNode.appendChild(pre);
            }
        }

        if (this._serverModal && !this._serverModal.isConnected) {
            document.body.appendChild(this._serverModal);
        }
    },

    closeServerResponse: function() {
        if (this._serverModal && this._serverModal.parentNode) {
            this._serverModal.parentNode.removeChild(this._serverModal);
        }
    },
    
    // ---------------------------------------------------------------- patching
    //
    // Every update used to be an innerHTML assignment, which throws away and
    // rebuilds the entire subtree. That is what makes the UI feel slow even when
    // the response is small: the browser loses scroll position, focus, text
    // selection and open <details>, and any JS object hung off a node (notably
    // Quill, which lives on container.__quill) is orphaned.
    //
    // patch() walks the new markup against the existing DOM and only touches
    // what actually differs. Children are matched by id, then by a "key"
    // attribute, then positionally.

    patchInner: true,

    /** element -> Set of event types already bound, so listeners are not stacked. */
    _bound: new WeakMap(),

    /** Elements that manage their own subtree and must not be reconciled. */
    _isPreserved: function(el) {
        return el.nodeType === 1 && (
            el.hasAttribute('data-preserve') ||
            el.classList.contains('ql-container') ||
            el.classList.contains('ql-editor') ||
            el.__quill !== undefined
        );
    },

    _keyOf: function(node) {
        if (node.nodeType !== 1) return null;
        return node.id || node.getAttribute('key') || null;
    },

    /** Replace the children of `target` with `html`, reusing what matches. */
    patch: function(target, html) {
        if (!target) return;
        const staging = document.createElement(target.tagName === 'TBODY' ? 'tbody' : 'div');
        if (target.tagName === 'TBODY' || target.tagName === 'TABLE' || target.tagName === 'TR') {
            // tbody/tr content cannot be parsed inside a <div>
            const t = document.createElement('template');
            t.innerHTML = String(html ?? '');
            staging.replaceChildren(...t.content.childNodes);
        } else {
            staging.innerHTML = String(html ?? '');
        }
        this._patchChildren(target, staging);
    },

    _patchNode: function(oldNode, newNode) {
        if (oldNode.nodeType !== newNode.nodeType || oldNode.nodeName !== newNode.nodeName) {
            oldNode.replaceWith(newNode.cloneNode(true));
            return;
        }
        if (oldNode.nodeType === 3 || oldNode.nodeType === 8) {           // text / comment
            if (oldNode.nodeValue !== newNode.nodeValue) oldNode.nodeValue = newNode.nodeValue;
            return;
        }
        if (oldNode.nodeType !== 1) return;

        this._patchAttributes(oldNode, newNode);
        if (this._isPreserved(oldNode)) return;                            // leave the subtree alone
        this._patchChildren(oldNode, newNode);
    },

    _patchAttributes: function(oldEl, newEl) {
        const oldAttrs = oldEl.attributes;
        for (let i = oldAttrs.length - 1; i >= 0; i--) {
            const name = oldAttrs[i].name;
            if (!newEl.hasAttribute(name)) oldEl.removeAttribute(name);
        }
        const newAttrs = newEl.attributes;
        for (let i = 0; i < newAttrs.length; i++) {
            const { name, value } = newAttrs[i];
            if (oldEl.getAttribute(name) !== value) oldEl.setAttribute(name, value);
        }

        // Form controls keep their live value in a property, not the attribute.
        // Only push the server's value when the server actually changed it —
        // otherwise whatever the user has typed survives the update.
        const tag = oldEl.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA') {
            const type = (oldEl.getAttribute('type') || '').toLowerCase();
            if (type === 'checkbox' || type === 'radio') {
                const want = newEl.hasAttribute('checked');
                if (oldEl.checked !== want) oldEl.checked = want;
            } else if (newEl.hasAttribute('value') && oldEl.value !== newEl.getAttribute('value')
                       && document.activeElement !== oldEl) {
                oldEl.value = newEl.getAttribute('value');
            }
        } else if (tag === 'OPTION') {
            const want = newEl.hasAttribute('selected');
            if (oldEl.selected !== want) oldEl.selected = want;
        }
    },

    _patchChildren: function(oldParent, newParent) {
        // Index the existing keyed children so a reorder moves nodes instead of
        // rebuilding them.
        const keyed = new Map();
        for (let n = oldParent.firstChild; n; n = n.nextSibling) {
            const k = this._keyOf(n);
            if (k) keyed.set(k, n);
        }

        let cursor = oldParent.firstChild;

        for (let incoming = newParent.firstChild; incoming; incoming = incoming.nextSibling) {
            const key = this._keyOf(incoming);
            let match = null;

            if (key) {
                match = keyed.get(key) || null;
                if (match) keyed.delete(key);
            } else if (cursor && !this._keyOf(cursor) && cursor.nodeName === incoming.nodeName) {
                match = cursor;                       // positional match for unkeyed nodes
            }

            if (match) {
                if (match !== cursor) oldParent.insertBefore(match, cursor);
                else cursor = cursor.nextSibling;
                this._patchNode(match, incoming);
            } else {
                oldParent.insertBefore(incoming.cloneNode(true), cursor);
            }
        }

        // Anything still sitting at or after the cursor was not in the new markup.
        while (cursor) {
            const next = cursor.nextSibling;
            oldParent.removeChild(cursor);
            cursor = next;
        }
    },

    getAllAttributes: function(el) {
        let attributes = {};
        if (!el || !el.attributes) return attributes;
        for (let i = 0; i < el.attributes.length; i++) {
            let attr = el.attributes[i];
            if (attr.name !== "class") {
                attributes[attr.name] = attr.value;
            }
        }
        return attributes;
    },

    /**
     * The payload for an interaction with `host`: its own attributes, folded
     * over those of the component enclosing it.
     *
     * The enclosing component matters because the control that was touched is
     * often only a part of it — a button inside a card, the hidden state input
     * inside a picker — and the handler needs the component's properties (its
     * comp, its id, its record) to know what it is acting on.
     *
     * The host's own attributes win on a name clash: the inner element is the
     * more specific description of what was interacted with.
     */
    componentPayload: function(host) {
        let obj = this.getAllAttributes(host);
        const parent = host && host.closest ? host.closest('[comp]') : null;
        if (parent && parent !== host) {
            obj = { ...this.getAllAttributes(parent), ...obj };
        }
        return obj;
    },

    handle: function(action) {
        ['name', 'target', 'code', 'type'].forEach(key => {
            if (action[key]) action[key] = action[key].trim();
        });

        if (action.name === "upload") {
            this.uploadChunks(action.target, action.code);
            return;
        }

        // These two do not address an element by selector, and both used to fall
        // through to the querySelectorAll below. 'refresh' carries an empty
        // target, and an empty string is not a valid selector — so it threw,
        // which aborted every remaining action in the same response. 'selectlast'
        // carries a form NAME, so it merely warned that no element matched.
        if (action.name === "refresh") {
            location.reload();
            return;
        }
        if (action.name === "selectlast") {
            const radioButtons = document.getElementsByName(action.target);
            const last = radioButtons[radioButtons.length - 1]
            if (last) last.checked = true;
            return;
        }

        if (!action.target) {
            console.warn(`Baustein: action "${action.name}" arrived with no target selector.`);
            return;
        }

        let targetElements;
        try {
            targetElements = document.querySelectorAll(action.target);
        } catch (err) {
            // Contained here on purpose: one malformed selector should cost you
            // that action, not the rest of the response.
            console.warn(`Baustein: "${action.target}" is not a valid selector (action "${action.name}").`, err);
            return;
        }

        // An action marked optional is a sweep — "remove any of these that are open" —
        // so matching nothing is the normal outcome, not a broken selector. Warning on
        // it meant every tab switch logged that it had found no panel to close.
        if (targetElements.length === 0 && !action.optional) {
            console.warn(`Baustein: No elements found for selector "${action.target}" in action "${action.name}"`);
        }
        
        for (let j = 0; j < targetElements.length; j++) {
            let targetElement = targetElements[j];
            if (targetElement) {
                if (action.name === "setAttribute" || action.name === "") {
                    let prop = action.code.split(":=>")
                    targetElement.setAttribute(prop[0], prop[1])
                    targetElement.dispatchEvent(new Event('change'))
                }
                if (action.name === "toggleAttribute") {
                    targetElement.toggleAttribute(action.code)
                }
                if (action.name === "removeAttribute") {
                    targetElement.removeAttribute(action.code)
                }
                if (action.name === "setval") {
                    targetElement.setAttribute("value", action.code)
                    targetElement.value = action.code
                    if (action.type!='')
                    targetElement.dispatchEvent(new Event(action.type))
                }
                if (action.name === "toggleval") {
                    if (targetElement.getAttribute("value") === "") {
                        targetElement.setAttribute("value", action.code)
                    } else {
                        let vals = targetElement.getAttribute("value").split('|')
                        const resultArr = vals.filter(arrObj => arrObj !== action.code)
                        if (resultArr.length === vals.length) resultArr.push(action.code)
                        targetElement.setAttribute("value", resultArr.join('|'))
                    }
                    targetElement.dispatchEvent(new Event('change'))
                }

                if (action.name === "inner") {
                    // Patch rather than replace. The end state is the same HTML,
                    // but reusing the nodes that did not change keeps scroll
                    // position, focus, text selection, open <details> and live
                    // editor instances alive. Set Baustein.patchInner = false to
                    // fall back to the old wholesale replacement.
                    if (this.patchInner) this.patch(targetElement, action.code);
                    else targetElement.innerHTML = action.code
                }
                if (action.name === "patch") {
                    this.patch(targetElement, action.code)
                }
                if (action.name === "child") {
                    targetElement.children.item(0).outerHTML = action.code
                }

                if (action.name === "exec") {
                    let val = document.querySelector(action.target).value;
                    if (action.code === "formatBlock") val = "PRE";
                    document.execCommand(action.code, false, val)
                }

                if (action.name === "parent") {
                    targetElement.parentElement.outerHTML = action.code
                }

                if (action.name === "grand") {
                    targetElement.parentElement.parentElement.outerHTML = action.code
                }

                if (action.name === "outer") {
                    targetElement.outerHTML = (action.code)
                }
                if (action.name === "toggle") {
                    targetElement.classList.toggle(action.code);
                }
                if (action.name === "add") {
                    if (action.code !== "") {
                        if (action.code.includes(" ")) {
                            let cs = action.code.trim().split(" ");
                            for (let i = 0; i < cs.length; i++) {
                                if (!targetElement.classList.contains(cs[i].trim()))
                                    targetElement.classList.add(cs[i].trim())
                            }
                        } else {
                            if (!targetElement.classList.contains(action.code))
                                targetElement.classList.add(action.code);

                        }
                    }
                }
                if (action.name === "remove") {
                    targetElement.parentElement.removeChild(targetElement);

                }

                if (action.name === "clear") {
                    targetElement.value = "";
                }
                if (action.name === "focus") {
                    targetElement.focus();
                    targetElement.select();
                }
                if (action.name === "strip") {
                    if (action.code !== "") {
                        if (action.code.includes(" ")) {
                            let cs = action.code.trim().split(" ");
                            for (let i = 0; i < cs.length; i++) {
                                if (cs[i].trim().includes("*")) {
                                    targetElement.classList.forEach(function (c) {
                                        if (c.includes(cs[i].trim().replace("*", "")))
                                            targetElement.classList.remove(c)
                                    })
                                } else {
                                    targetElement.classList.remove(cs[i].trim())
                                }
                            }
                        } else {
                            if (action.code.trim().includes("*")) {
                                targetElement.classList.forEach(function (c) {
                                    if (c.includes(action.code.trim().replace("*", "")))
                                        targetElement.classList.remove(c);
                                })
                            } else {
                                targetElement.classList.remove(action.code);
                            }

                        }
                    }

                }

                if (action.name === "value") {
                    targetElement.setAttribute("value", action.code);
                }

                if (action.name === "open") {
                    targetElement.setAttribute("open", "open");
                }

                if (action.name === "click") {
                    setTimeout(() => {
                        targetElement.click();
                    }, action.delay ?? 0)
                }

                if (action.name === "plant") {
                    const selection = window.getSelection();
                    const range = selection.getRangeAt(0);
                    const rect = range.getBoundingClientRect();
                    // Set context menu position
                    targetElement.style.left = "0px";
                    targetElement.style.top = "0px";
                    targetElement.style.display = "block";
                    targetElement.classList.remove("hidden")

                }

                if (action.name === "append") {
                    let div = document.createElement("div");
                    div.innerHTML = action.code;
                    let received = div.children[0];
                    const old = document.getElementById(received.id);
                    if (old) {
                        old.replaceWith(received)
                    } else {
                        targetElement.appendChild(received)
                    }

                }

                if (action.name === "before") {
                    targetElement.insertAdjacentHTML('beforebegin', action.code);
                }

                if (action.name === "call") {
                    let func = action.func;

                    const parseArgs = (argStr) => {
                        if (!argStr) return [];
                        const s = String(argStr).trim();
                        if (!s.length) return [];
                        if (s.startsWith('[') && s.endsWith(']')) {
                            try {
                                const parsed = JSON.parse(s);
                                return Array.isArray(parsed) ? parsed : [parsed];
                            } catch (_) {}
                        }

                        const args = [];
                        let cur = '';
                        let depth = 0;
                        let quote = null;
                        for (let i = 0; i < s.length; i++) {
                            const ch = s[i];
                            if (quote) {
                                cur += ch;
                                if (ch === quote && s[i - 1] !== '\\') quote = null;
                                continue;
                            }
                            if (ch === '"' || ch === "'") {
                                quote = ch;
                                cur += ch;
                                continue;
                            }
                            if (ch === '{' || ch === '[' || ch === '(') {
                                depth++;
                                cur += ch;
                                continue;
                            }
                            if (ch === '}' || ch === ']' || ch === ')') {
                                depth = Math.max(0, depth - 1);
                                cur += ch;
                                continue;
                            }
                            if (ch === ',' && depth === 0) {
                                args.push(cur.trim());
                                cur = '';
                                continue;
                            }
                            cur += ch;
                        }
                        if (cur.trim().length) args.push(cur.trim());
                        return args;
                    };

                    // Resolve a (possibly dotted) name to a function on window,
                    // e.g. "Slider.open" or "successToaster". Replaces eval():
                    // the server used to hand this branch a string that was
                    // executed verbatim, so anything able to shape a response
                    // could run arbitrary script in the page.
                    const resolve = (path) => {
                        if (typeof path !== 'string' || !path) return null;
                        if (!/^[A-Za-z_$][\w$]*(\.[A-Za-z_$][\w$]*)*$/.test(path)) return null;
                        let ctx = window, fn = window;
                        for (const part of path.split('.')) {
                            if (fn == null) return null;
                            ctx = fn;
                            fn = fn[part];
                        }
                        return typeof fn === 'function' ? { fn, ctx } : null;
                    };

                    const callNamed = (name, args) => {
                        const target = resolve(name);
                        if (!target) {
                            console.error('Baustein: no such function "' + name + '"');
                            return;
                        }
                        // parseArgs already yields the values eval() would have
                        // produced from the JSON literals, so apply them directly.
                        target.fn.apply(target.ctx, args);
                    };

                    const invoke = () => {
                        if (action?.details) {
                            callNamed(func, parseArgs(action.details));
                        } else if (action?.code) {
                            let allcode = action.code.split("|");
                            callNamed(allcode[0] ?? null, parseArgs(allcode[1] ?? ""));
                        } else {
                            console.error("action.code is undefined");
                        }
                    };
                    const d = Number(action.delay || 0);
                    if (d > 0) {
                        setTimeout(invoke, d);
                    } else {
                        invoke();
                    }

                }
                

                if (action.name === "absolute") {
                    const dir = document.querySelector('html').getAttribute('dir')
                    if (dir === "rtl") {
                        targetElement.style.left = (pageX - 200) + 'px';
                    } else {
                        targetElement.style.left = pageX + 'px';

                    }
                    targetElement.style.bottom = `${window.innerHeight - pageY}px`;
                }

                if (action.name === "scrollView") {
                    targetElement.scrollIntoView({block: 'center', behavior: 'smooth'});
                }
                if (action.name === "shimmered") {
                    // generateShimmer is likely a global function?
                    if (window.generateShimmer) window.generateShimmer(targetElement.getAttribute("key"), action.code)
                }
            }
        }
    },

    xhandle: async function(functions, evt = null, bypassConfirm = false) {        
        let e = evt || window.event;
        if (!e && this.currentTarget) {
            e = { type: 'load', target: this.currentTarget, preventDefault: ()=>{}, stopPropagation: ()=>{} };
        }
        if (!e) return;

        const eventTypeStart = e.type;
        const eventHost = e.currentTarget || e.target;
        if (!bypassConfirm && eventTypeStart === 'click' && eventHost && eventHost.getAttribute) {
            const confirmText = eventHost.getAttribute('x-confirm');
            if (confirmText && String(confirmText).trim() !== '') {
                if (e.preventDefault) e.preventDefault();
                if (e.stopPropagation) e.stopPropagation();
                const capturedTarget = eventHost;
                const capturedEvent = {
                    type: 'click',
                    target: capturedTarget,
                    currentTarget: capturedTarget,
                    preventDefault: ()=>{},
                    stopPropagation: ()=>{},
                };
                this.confirm(confirmText, () => {
                    this.xhandle(functions, capturedEvent, true);
                });
                return;
            }
        }
        
        let delay = 0;
        if (functions === '') return;
        for (let func of functions.split('|')) {
            if (func.startsWith('$')) {
                let action = {};
                action.name = func.substring(1).split("(")[0];
                let params = func.substring(1).replace(action.name + "(", "").slice(0, -1);
                action.target = params.split(",")[0];

                action.code = params.split(",")[1];
                setTimeout(() => {
                    this.handle(action)
                }, 0)
                continue;
            }
            const eventType = e.type;

            // Attributes come from the element the handler is attached to, which
            // is not necessarily the one that was clicked: a click on a button's
            // inner <i> makes e.target the icon. The previous version did
            // document.getElementById(e.target.id) — for any control with child
            // elements that is getElementById(''), which returns null, and the
            // next line threw "Cannot read properties of null".
            const host = (e.currentTarget && e.currentTarget.nodeType === 1)
                ? e.currentTarget
                : ((e.target && e.target.nodeType === 1) ? e.target : null);
            if (!host) continue;

            // An interaction that starts outside every slider is an interaction about
            // something else, and any slider still on screen is now showing stale
            // context — the team of a project you have just navigated away from, a
            // half-filled "new project" form you have abandoned by clicking a tab.
            //
            // Done here rather than in each handler because there is no handler that
            // wants the opposite: a control *inside* a slider is in scope and closes
            // nothing, which host.closest('.slider') is exactly the test for.
            //
            // Restricted to the two event types a person actually initiates. 'load'
            // and friends fire while content renders, which for a template using
            // onload="xhandle(...)" would close a slider the instant it opened.
            if ((eventType === 'click' || eventType === 'change')
                && window.Slider && typeof window.Slider.closeAll === 'function'
                && typeof host.closest === 'function' && !host.closest('.slider')) {
                window.Slider.closeAll();
            }

            let obj = this.componentPayload(host)

            if (eventType !== "dragstart") e.preventDefault()

            if (eventType === "mousedown")
                if (e.which !== 2)
                    continue;

            e.stopPropagation()
            obj.original = e
            if (eventType === "input") {
                delay = 300;
                obj.value = e.target.value;
                if (obj.value === undefined) obj.value = e.target.innerText;
            }
            if (eventType === "blur") {
                if (e.target.tagName.toLowerCase() === "div") {
                    obj.value = e.target.innerText;
                } else {
                    obj.value = e.target.value;
                }

            }
            if (eventType === "change") {
                // If it's a multiple select, get all values, else get the standard value
                if (e.target && e.target.tagName === "SELECT" && e.target.multiple) {
                    obj.value = Array.from(e.target.selectedOptions).map(o => o.value);
                } else if (e.target && e.target._multiValues) {
                    // Fallback for custom pickers that set this property
                    obj.value = e.target._multiValues;
                } else {
                    obj.value = e.target.value;
                }

                if (e.target && e.target.tagName === "INPUT" && e.target.type === "file") {
                    delay = 1000;
                    const file = e.target.files[0]; 

                    if (file) {

                        const fileName = file.name;
                        const fileType = file.type;
                        const reader = new FileReader();

                        reader.onload = function (e) {
                            const base64String = e.target.result.split(",");
                            obj.value = base64String[1] + "<|>" + fileType + "<|>" + fileName

                        };

                        reader.readAsDataURL(file);
                    }
                }

            }
            if (eventType === "dragover") continue;

            if (eventType === "dragstart") {
                e.dataTransfer.dropEffect = "move";
                e.dataTransfer.setData("Text", e.target.id)
                continue;
            }
            if (eventType === "drop") {
                const column = event.target.closest('.custom-drop');
                if (column) obj = this.getAllAttributes(column)
                obj.moved = e.dataTransfer.getData("Text");

            }

            if (eventType === "submit") {
                var formElement = e.target;  

                var fd = new FormData(formElement);
                var formDataObj = {};

                fd.forEach((value, key) => {
                    key = String(key || '').trim();
                    while (key.endsWith('[]')) key = key.slice(0, -2);
                    if (key in formDataObj) {
                        if (!Array.isArray(formDataObj[key])) {
                            formDataObj[key] = [formDataObj[key]];  
                        }
                        formDataObj[key].push(value);  
                    } else {
                        formDataObj[key] = value;  
                    }
                });

                obj.value = JSON.stringify(formDataObj);
                //e.target.querySelector('.mbs-submit-button').classList.add('disabled')
                //e.target.querySelector('.loading-icon').classList.remove('hidden')

            }

            if ((eventType === "click") || (eventType === "mousedown")) {
                // These all describe the control that was wired up, so they read
                // from `host`. Using e.target meant the inner <i> of an icon
                // button, which carries none of these attributes — and
                // getAttribute("id") on it returned null, so the .includes()
                // below threw for every icon-only button in the app.
                // Reflect the interaction in the address bar, so what the user
                // is looking at can be linked to and survives a reload.
                //
                // Built from the current location. It used to be built from a
                // hardcoded hostname, with a second hardcoded hostname in the
                // catch — which meant that anywhere other than those two
                // machines it wrote a cross-origin URL, threw SecurityError,
                // and threw again in the handler.
                if (host.hasAttribute('data-target')) {
                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('target', host.getAttribute('data-target'));
                        history.pushState(null, '', url);
                    } catch (err) {
                        console.warn('Baustein: could not update the address bar.', err);
                    }
                }

                if ((host.getAttribute("id") || '').includes('submit-modal-')) {
                    host.disabled = true;
                }

                if (host.classList.contains("form-submit")) {
                    obj.value = {}

                    let builder = "";
                    host.classList.forEach(function (c) {
                        if (c.includes("formbuilder")) builder = c;
                    })

                    host.parentElement.parentElement.querySelectorAll("." + builder).forEach(function (el) {

                        if (!el.classList.contains("form-submit")) {

                            el.blur();
                            if (el.tagName === 'INPUT') obj.value[el.id] = el.value;
                            else
                                obj.value[el.id] = el.getAttribute("value");

                            if (el.classList.contains("Repeater")) {
                                let val = [];
                                let elements = el.children[0];
                                for (let i = 0; i < elements.children.length; i++) {
                                    let ch = elements.children[i];
                                    let row = [];
                                    for (let j = 0; j < ch.children.length; j++) {

                                        if (ch.children[j].tagName === 'INPUT') row.push(ch.children[j].value);
                                        else
                                            row.push(ch.children[j].getAttribute("value"));
                                    }
                                    val.push(row);
                                }
                                obj.value[el.id] = val;
                            }

                        }
                    })

                    func = func.split('(')[1] + '()'
                }

            }


            /*if (func.includes("##")) {


                let funcname = func.split('(')[0];
                let withoutFunc = func.split('(')[1];

                let onlyparams = withoutFunc.substring(0, withoutFunc.length - 1)
                let params = onlyparams.split(',')
                let newparams = params.map(p => p.trim());
                for (let k = 0; k < newparams.length; k++) {
                    if (newparams[k].startsWith('##')) {
                        let content = document.getElementById(newparams[k].substring(2));
                        let content64 = "";
                        if (content.tagName.toLowerCase() === "input") content64 = btoa(unescape(encodeURIComponent(content.value))); 
                        if (content.tagName.toLowerCase() === "div") content64 = btoa(unescape(encodeURIComponent(content.innerHTML.replace("&nbsp;", ""))))
                        newparams[k] = content64;
                    }
                }
                func = funcname + "(" + newparams.join(',') + ')';
            }*/

            obj.func = func;

            this.debounced(host, obj, delay);
        }
    },

    /**
     * Send now, or after $delay ms with any earlier pending send for THIS
     * element cancelled.
     *
     * Keyed by element, so a slow typist in one field cannot cancel another
     * field's update. A WeakMap, so an element removed from the DOM takes its
     * timer entry with it.
     */
    debounced: function(host, obj, delay) {
        if (!delay || delay <= 0) {
            this.sendUpdate(obj);
            return;
        }
        const pending = this._timers.get(host);
        if (pending) clearTimeout(pending);
        this._timers.set(host, setTimeout(() => {
            this._timers.delete(host);
            this.sendUpdate(obj);
        }, delay));
    },

    setEvents: function(html) {
        // console.log("event refreshing:",html)
        let allowed=["click","keyup","change","blur","input","load", "drop","dragstart", "dragover","dragend"]
        let items;
        if (html.nodeType === Node.ELEMENT_NODE) {
            items = html.querySelectorAll("[actions], [onload]");
            // Also check if html itself has attributes
            if (html.hasAttribute("actions") || html.hasAttribute("onload")) {
                // If html is an element that needs processing, include it.
                // But items is a NodeList. We can make an array.
                items = Array.from(items);
                items.push(html);
            }
        } else {
             items = html.querySelectorAll("[actions], [onload]");
        }


        // if (html.children && html.children.length===0) items=[html]; // This logic was flawed if html has children but also has actions itself.

        if (!items) return;

        items.forEach(item => {
             // Handle "onload" attribute directly if present (legacy support or direct attribute usage)
             if (item.hasAttribute("onload") && !item.hasAttribute("data-loaded")) {
                 item.setAttribute("data-loaded", "true");
                 let func = item.getAttribute("onload");
                 // onload="xhandle('Projects.tasks()')"
                 // We need to execute this.
                 // If it calls xhandle, we can just eval it or call xhandle.
                 // But better to parse it if it follows xhandle pattern, or just eval if it's JS.
                 // The user example: onload="xhandle('Projects.tasks()')"
                 // This is standard JS in HTML attribute. 
                 // But wait, the browser only fires 'onload' event on specific elements (img, script, body, etc.).
                 // It DOES NOT fire on div/details/etc automatically when added to DOM.
                 // That's why we need to handle it manually here in setEvents.
                 
                 // Execute the JS code in the attribute
                  try {
                      this.currentTarget = item;
                      const execute = new Function(func);
                      execute.call(item);
                      this.currentTarget = null;
                  } catch (e) {
                      console.error("Error executing onload:", e, item);
                      this.currentTarget = null;
                  }
             }

            // Which event types this element already has a listener for.
            // Without this, re-processing an element (a node moved rather than
            // replaced still arrives as an "added node" at the MutationObserver)
            // stacked a second identical listener and fired every handler twice.
            let bound = this._bound.get(item);
            if (!bound) { bound = new Set(); this._bound.set(item, bound); }

            for (let j = 0; j < allowed.length; j++) {
                // Skip load here as we handled it separately via onload attribute or xonload
                if (allowed[j] === "load") {
                    if (item.hasAttribute("xon"+allowed[j]) && !item.hasAttribute("data-xonloaded")) {
                        item.setAttribute("data-xonloaded", "true");
                        // The handler is the attribute's VALUE. This used to send
                        // the attribute's NAME — literally "xonload" — so the
                        // server was asked for a handler by that name, on no
                        // component, and refused every such request.
                        let obj = this.componentPayload(item);
                        obj.func = item.getAttribute("xon"+allowed[j]);
                        this.triggerAction(item, "load", obj);
                    }
                    continue;
                }

                if (!item.hasAttribute("actions")) continue; // Only process xon* events if actions="on"
                if (!item.hasAttribute("xon"+allowed[j])) continue;
                if (item.getAttribute("xon"+allowed[j])==="") continue;
                if (bound.has(allowed[j])) continue;

                bound.add(allowed[j]);
                item.addEventListener(allowed[j], (e) => {
                    // Read the attributes now, not when the listener was created.
                    // Patching updates attributes in place on nodes it reuses, so
                    // a set captured at bind time would send stale values. It also
                    // means we no longer retain one full copy of every element's
                    // attributes per event type.
                    const obj = this.componentPayload(item);
                    obj.func = item.getAttribute("xon" + allowed[j]);
                    this.triggerAction(item, allowed[j], obj, e);
                })
            }
        });
    },

    triggerAction: function(item, eventType, obj, e = null) {
         if (eventType!=="dragstart" && e) e.preventDefault()
         if (e) e.stopPropagation()
         if (e) obj.original=e
         
         if (eventType==="input" && e) obj.value=e.target.value;
         if (eventType==="blur" && e) obj.value=e.target.value;
         if (eventType==="change" && e) obj.value=e.target.value; // ensure change is captured if not handled below

         // If the listener is attached directly to a form, serialize the whole form
         if (['change', 'blur', 'input', 'submit'].includes(eventType) && item.tagName === 'FORM') {
             var fd = new FormData(item);
             var formDataObj = {};
             fd.forEach((value, key) => {
                 // Remove '[]' from array keys like 'status-filter[]' to match backend expectation 'status-filter'
                 let cleanKey = key.endsWith('[]') ? key.slice(0, -2) : key;
                 
                 if (cleanKey in formDataObj) {
                     if (!Array.isArray(formDataObj[cleanKey])) formDataObj[cleanKey] = [formDataObj[cleanKey]];  
                     formDataObj[cleanKey].push(value);  
                 } else {
                     // For multiple selects, we always want an array even if 1 item is selected
                     // We can check if the original key ended with []
                     if (key.endsWith('[]')) {
                         formDataObj[cleanKey] = [value];
                     } else {
                         formDataObj[cleanKey] = value;  
                     }
                 }
             });
             obj.value = formDataObj; // Send as object, not JSON string, so backend request->get() parses it correctly
         }

         if (eventType==="dragover") return;

         if (eventType==="dragstart" && e){
             e.dataTransfer.dropEffect="move";
             e.dataTransfer.setData("Text",e.target.id)
             return;
         }
         if (eventType==="drop" && e){
             obj.moved=e.dataTransfer.getData("Text");
         }


         /*if (item.getAttribute("xon"+eventType).includes("##")){
             let func=item.getAttribute("xon"+eventType);

             let funcname=func.split('(')[0];
             let withoutFunc=func.split('(')[1];

             let onlyparams=withoutFunc.substring(0,withoutFunc.length-1)
             let params= onlyparams.split(',')
             let newparams=params;
             for (let k = 0; k < params.length; k++) {

                 if (params[k].startsWith('##')) {
                     newparams[k]= document.getElementById(params[k].substring(2)).value;
                 }
             }
             obj["xon"+eventType]=funcname+"("+newparams.join(',')+')';
         }*/

         // Typing debounces here too, so the two wiring styles behave alike.
         this.debounced(item, obj, eventType === 'input' ? 300 : 0);
    },

    uploadChunks: async function(targetSelector, code) {
        const target = document.querySelector(targetSelector);
        if (!target) return;

        const fileInput = (target.tagName === "INPUT" && target.type === "file")
            ? target
            : target.querySelector('input[type="file"]');
        if (!fileInput || !fileInput.files || !fileInput.files[0]) return;

        const parts = String(code || '').split('::');
        const handler = (parts[0] || '').trim();
        const chunkSize = parseInt(parts[1] || '60000', 10) || 60000;
        if (!handler) return;

        const file = fileInput.files[0];
        const fileName = file.name || '';
        const fileType = file.type || 'application/octet-stream';

        const dataUrl = await new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target?.result || '');
            reader.readAsDataURL(file);
        });

        const str = String(dataUrl || '');
        const idx = str.indexOf(',');
        const base64 = idx >= 0 ? str.slice(idx + 1) : '';
        if (!base64) return;

        const total = Math.ceil(base64.length / chunkSize);
        const parentComp = fileInput.closest('[comp]');
        let baseObj = this.getAllAttributes(fileInput);
        if (parentComp) {
            const parentAttrs = this.getAllAttributes(parentComp);
            baseObj = { ...parentAttrs, ...baseObj };
            if (parentComp.id) baseObj.id = parentComp.id;
        }

        const uploadId = (baseObj.id || fileInput.id || 'upload') + '-' + Date.now() + '-' + Math.random().toString(16).slice(2);

        for (let i = 0; i < total; i++) {
            const chunk = base64.slice(i * chunkSize, (i + 1) * chunkSize);
            const obj = {
                ...baseObj,
                func: handler,
                value: {
                    upload_id: uploadId,
                    chunk_index: i,
                    chunk_total: total,
                    chunk: chunk,
                    file_name: fileName,
                    file_type: fileType,
                    is_last: i === total - 1
                }
            };
            await this.sendUpdate(obj);
        }
    },

    // Read at send time from the meta tag the server puts in <head>. Not from a
    // global set by an inline script: Template::process() moves every script to
    // the end of the body, so such a global is not defined yet when Baustein
    // initialises and fires the first onload handlers.
    csrfToken: function() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        return window.CSRF_TOKEN || '';
    },

    sendUpdate: async function(obj) {
        document.body.classList.add('waiting')
        const formData = new FormData();
        // Proves the request came from a page the server issued. Without it the
        // endpoint accepted state-changing POSTs from any origin.
        obj = Object.assign({}, obj, { csrf: Baustein.csrfToken() });
        formData.append('data', JSON.stringify(obj));
        // Resolved against the DIRECTORY of the current page, not the page URL:
        // the previous form appended "updater.php" straight onto the href, which
        // is right for "/app/" and produces "/app/index.phpupdater.php" for
        // "/app/index.php" — so the endpoint was unreachable on any URL that
        // named the script.
        await fetch(window.location.pathname.replace(/[^/]*$/, '') + 'updater.php', {
            method: 'POST',
            headers: {'Cache-Control': 'no-cache', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: formData
        })
            .then(async function (response) {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    const raw = String(text || '');
                    const t = raw.trim();
                    const looksHtml =
                        (t.startsWith('<') && t.includes('>')) ||
                        /<\s*pre\b/i.test(t) ||
                        /<\s*html\b/i.test(t) ||
                        /<\s*body\b/i.test(t);
                    return { status: 'error', message: looksHtml ? '' : (raw || 'Invalid server response'), html: looksHtml ? raw : '' };
                }
            })
            .then((data) => {
                // Backward compatibility: if data is array, treat as actions only
                let responseData = Array.isArray(data) ? { status: 'ok', message: '', actions: data } : data;
                
                if (responseData?.res) { // Legacy check? Or maybe remove this if no longer used
                    location.reload(true);
                }
                
                if (responseData.status !== 'ok') {
                    // warn, not log, and it says which call failed: this used to print
                    // "Error/Warning:" followed by nothing whenever the message was
                    // empty, which told you something had gone wrong but not what or
                    // where.
                    console.warn('Baustein: ' + (obj && obj.class ? obj.class + '.' + obj.method : 'a call')
                        + ' returned status "' + responseData.status + '"',
                        responseData.message || '(no message)');
                    if (responseData.html) {
                        this.showServerResponse(responseData.html, true);
                        return;
                    }
                    if (responseData.message) {
                        const msg = String(responseData.message || '');
                        const t = msg.trim();
                        const looksHtml =
                            (t.startsWith('<') && t.includes('>')) ||
                            /<\s*pre\b/i.test(t) ||
                            /<\s*html\b/i.test(t) ||
                            /<\s*body\b/i.test(t);
                        // Plain text is something a person is meant to read, so it
                        // gets a message box. Only markup — a debug dump or a
                        // rendered exception — goes to the "Server response" panel.
                        if (looksHtml) this.showServerResponse(msg, true);
                        else this.alert(msg);
                        return;
                    }

                }

                if (responseData.actions) {
                    for (let i = 0; i < responseData.actions.length; i++) {
                        let action = responseData.actions[i];

                        // A "more" template: the action's code is a list of data
                        // objects and `more` is the markup to repeat for each,
                        // with {{$key}} standing in for a value.
                        if (action.more && action.more !== "") {
                            if (action.code && (Array.isArray(action.code) || typeof action.code === 'string')) {
                                let template = action.more;
                                let dataList = action.code;
                                if (typeof dataList === 'string') {
                                    try {
                                        dataList = JSON.parse(dataList);
                                    } catch (e) {
                                        dataList = [dataList];
                                    }
                                }
                                if (!Array.isArray(dataList)) dataList = [dataList];

                                let generatedHtml = "";
                                dataList.forEach(dataItem => {
                                    let itemHtml = template;
                                    if (typeof dataItem === 'object') {
                                        for (const [key, value] of Object.entries(dataItem)) {
                                            itemHtml = itemHtml.replace('{{$' + key + '}}', value);
                                        }
                                    } else {
                                        itemHtml = itemHtml.replace(new RegExp('{{value}}', 'g'), dataItem);
                                    }
                                    generatedHtml += itemHtml;
                                });

                                action.code = generatedHtml;
                            }
                        }

                        this.handle(action);
                    }
                }
            }).catch(function (err) {
                console.warn('Baustein: the request failed.', err);
            }).finally(function () {
                // Always clear the loading state. Previously the two error
                // branches above returned early and .catch() never cleared it,
                // so any server error or dropped connection left the whole UI
                // stuck in 'waiting' until a page reload.
                document.body.classList.remove('waiting');
            });
    },
    
    init: function() {
        this.setEvents(document);
        const observer = new MutationObserver(mutationsList => {
            for (let mutation of mutationsList) {
                for (let i = 0; i < mutation.addedNodes.length; i++) {
                    if (mutation.addedNodes[i].nodeName==="#text") continue;
                    this.setEvents(mutation.addedNodes[i])
                }
            }
        });
        observer.observe(document.body, { childList: true,subtree: true });
    }
};

// Global Exposure
window.getAllAttributes = Baustein.getAllAttributes;
window.handle = Baustein.handle.bind(Baustein);
window.xhandle = Baustein.xhandle.bind(Baustein);
window.setEvents = Baustein.setEvents.bind(Baustein);
window.Baustein = Baustein;

// Auto-init at the end of body execution (when script runs)
// Since this script is moved to end of body by Template.php, it's safe.
Baustein.init();

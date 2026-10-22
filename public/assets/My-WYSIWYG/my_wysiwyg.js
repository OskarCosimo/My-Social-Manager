/**
 * my_wysiwyg.js
 * Core class for My WYSIWYG
 */
class MyWysiwyg {
    constructor(selector, options = {}) {
        this.textarea = typeof selector === 'string' ? document.querySelector(selector) : selector;
        
        if (!this.textarea || this.textarea.tagName !== 'TEXTAREA') {
            console.error('MyWysiwyg: Target element must be a textarea.');
            return;
        }

        // Updated default options with full formatting suite
        this.options = Object.assign({
            lang: 'en',
            maxChars: Infinity,
            minHeight: '150px', // Default minimum height
            maxHeight: '400px', // Default maximum height before scrollbars
            placeholder: this.textarea.getAttribute('placeholder') || '',
            toolbar: [
                'fontName', 'fontSize', 'foreColor', '|',
                'bold', 'italic', 'underline', '|', 
                'alignLeft', 'alignCenter', 'alignRight', 'alignJustify', '|',
                'ul', 'ol', '|', 
                'link', 'image', 'video', 'emoji', '|', 
                'code', 'hr', '|', 
                'undo', 'redo', '|', 
                'html'
            ],
            emojis: {
                'Smileys': ['😀','😃','😄','😁','😆','😅','😂','🤣','🥲','☺️','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥸','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕'],
                'Gestures & People': ['👋','🤚','🖐','✋','🖖','👌','🤌','🤏','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👍','👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦾','🦿','🦵','🦶','👂','🦻','👃','🧠','🫀','🫁','🦷','👀','👁','👅','👄','💋','🩸'],
                'Nature': ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐻‍❄️','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🐔','🐧','🐦','🐤','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🐛','🦋','🐌','🐞','🐜','🕷','🦂','🐢','🐍','🦎','🦖','🦕','🐙','🦑','🦐','🦞','🦀','🐡','🐠','🐟','🐬','🐳','🦈','🐊','🐅','🐆','🦓','🦍','🐘','🦛','🦏','🐪','🦒','🦘','🐃','🐂','🐄','🐎','🐖','🐏','🐑','🦙','🐐','🦌','🐕','🐈','🌲','🌳','🌴','🌵','🌾','🌿','☘️','🍀','🍁','🍂','🍃','🍄','🌍','🌕','☀️','⭐','☁️','⛈','🌤','🌧','🌨','🌩','🌪','🌈','⚡','❄️','🔥','💧','🌊'],
                'Food & Drink': ['🍏','🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🍈','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🍆','🥑','🥦','🥬','🥒','🌶','🌽','🥕','🧄','🧅','🥔','🍠','🥐','🥯','🍞','🥖','🥨','🧀','🥚','🍳','🧈','🥞','🧇','🥓','🥩','🍗','🍖','🌭','🍔','🍟','🍕','🥪','🌮','🌯','🥗','🥘','🥫','🍝','🍜','🍲','🍛','🍣','🍱','🥟','🍤','🍙','🍚','🍘','🍧','🍨','🍦','🥧','🧁','🍰','🎂','🍮','🍭','🍬','🍫','🍿','🍩','🍪','🌰','🥜','🍯','🥛','🍼','☕','🍵','🧃','🥤','🍺','🍻','🥂','🍷','🥃','🍸','🍹','🍾','🧊'],
                'Objects & Symbols': ['⌚','📱','💻','⌨️','🖥','🖨','🖱','📷','📹','🎥','📞','☎️','📺','📻','🎙','⏱','⏰','⏳','🔋','🔌','💡','🔦','💸','💵','💶','💷','💰','💳','💎','⚖️','🧰','🔧','🔨','⚒','⚙️','🧱','🔫','💣','🧨','🔪','🗡','⚔️','🛡','🚬','🔮','🩹','🩺','💊','💉','🩸','🧬','🦠','🧪','🌡','🧹','🧻','🚽','🚿','🛁','🧼','🔑','🚪','🛏','🧸','🖼','🛍','🛒','🎁','🎈','🎀','🎊','🎉','✉️','📦','📊','📈','📉','🗓','📅','🗑','📋','📁','📂','📰','📓','📚','📖','🔖','🔗','📎','✂️','🖊','✏️','🔍','🔎','🔒','🔓','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','✅','❌','⭕','🛑','⛔','🚫','💯','❗','❓','❕','❔','🎵','🎶']
            }
        }, options);

        this.i18n = typeof MyWysiwygI18n !== 'undefined' && MyWysiwygI18n[this.options.lang] 
                    ? MyWysiwygI18n[this.options.lang] 
                    : { chars: 'Chars', editLink: 'Edit', removeLink: 'Unlink' }; 
                    
        this.isHtmlView = false;
        this.savedSelection = null;
        this.activeLinkNode = null;

        this._init();
    }

    _init() {
        this.textarea.classList.add('my-wysiwyg-hidden');
        this.container = document.createElement('div');
        this.container.className = 'my-wysiwyg-container';

        this._buildEmojiPicker(); // Build early
        this._buildToolbar();
        this._buildEditor();
        this._buildLinkTooltip();
        this._buildStatusBar();

        this.container.appendChild(this.toolbar);
        this.container.appendChild(this.editor);
        this.container.appendChild(this.linkTooltip);
        this.container.appendChild(this.emojiPicker); // Append directly to main container
        this.container.appendChild(this.statusbar);
        this.textarea.parentNode.insertBefore(this.container, this.textarea);

        document.addEventListener('click', (e) => {
            // Close emoji picker if clicking outside both the picker and the trigger button
            if (this.emojiPicker && !e.target.closest('.my-wysiwyg-emoji-picker') && !e.target.closest('.btn-emoji-trigger')) {
                this.emojiPicker.classList.remove('show');
            }
            if (this.linkTooltip && !e.target.closest('.my-wysiwyg-editor') && !e.target.closest('.my-wysiwyg-link-tooltip')) {
                this._hideLinkTooltip();
            }
        });

        document.execCommand('defaultParagraphSeparator', false, 'p');
        this.editor.innerHTML = this.textarea.value;
        this._updateCharCount();
        this._checkEmptyState();
    }

    _buildToolbar() {
        this.toolbar = document.createElement('div');
        this.toolbar.className = 'my-wysiwyg-toolbar';

        this.options.toolbar.forEach(action => {
            if (action === '|') {
                const divider = document.createElement('div');
                divider.className = 'my-wysiwyg-divider';
                this.toolbar.appendChild(divider);
                return; 
            }

            // Custom UI: Font Family Dropdown
            if (action === 'fontName') {
                const select = document.createElement('select');
                select.className = 'my-wysiwyg-select';
                select.title = this.i18n.fontName || 'Font';
                const fonts = ['Arial', 'Courier New', 'Georgia', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'];
                fonts.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f; opt.innerText = f;
                    select.appendChild(opt);
                });
                select.addEventListener('change', (e) => {
                    this._executeCommand('fontName', e.target.value);
                    select.selectedIndex = 0; // Reset after selection
                });
                this.toolbar.appendChild(select);
                return;
            }

            // Custom UI: Font Size Dropdown
            if (action === 'fontSize') {
                const select = document.createElement('select');
                select.className = 'my-wysiwyg-select';
                select.title = this.i18n.fontSize || 'Size';
                const sizes = [
                    {val: '1', txt: 'Small'}, 
                    {val: '3', txt: 'Normal'}, 
                    {val: '5', txt: 'Large'}, 
                    {val: '7', txt: 'Huge'}
                ];
                sizes.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.val; opt.innerText = s.txt;
                    select.appendChild(opt);
                });
                select.selectedIndex = 1; // Default to normal
                select.addEventListener('change', (e) => this._executeCommand('fontSize', e.target.value));
                this.toolbar.appendChild(select);
                return;
            }

            // Custom UI: Font Color Picker Native
            if (action === 'foreColor') {
                const input = document.createElement('input');
                input.type = 'color';
                input.className = 'my-wysiwyg-color-picker';
                input.title = this.i18n.foreColor || 'Color';
                input.value = '#212529'; // Default black/dark gray
                input.addEventListener('input', (e) => this._executeCommand('foreColor', e.target.value));
                this.toolbar.appendChild(input);
                return;
            }

            // Custom UI: Emoji Dropdown (Dynamic Positioning)
            if (action === 'emoji') {
                const btn = this._createButton(action);
                btn.classList.add('btn-emoji-trigger'); // Custom class for outside-click detection
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this._toggleEmojiPicker(btn);
                });
                this.toolbar.appendChild(btn);
                return;
            }

            // Standard Buttons
            const btn = this._createButton(action);
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this._executeCommand(action);
            });
            this.toolbar.appendChild(btn);
        });

        const expandBtn = document.createElement('button');
        expandBtn.className = 'my-wysiwyg-btn expand-btn';
        expandBtn.type = 'button';
        expandBtn.title = this.i18n.expand || 'Expand';
        expandBtn.innerHTML = `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>`;
        expandBtn.addEventListener('click', () => {
            this.toolbar.classList.toggle('expanded');
        });
        this.toolbar.appendChild(expandBtn);
    }

    _createButton(action) {
        const btn = document.createElement('button');
        btn.className = 'my-wysiwyg-btn';
        btn.type = 'button';
        btn.title = this.i18n[action] || action;
        btn.innerHTML = this._getIconForAction(action);
        return btn;
    }

    _buildEmojiPicker() {
        this.emojiPicker = document.createElement('div');
        this.emojiPicker.className = 'my-wysiwyg-emoji-picker';
        
        // Iterate through each category defined in options
        for (const [categoryName, emojiArray] of Object.entries(this.options.emojis)) {
            
            // Create category container
            const categorySection = document.createElement('div');
            categorySection.className = 'my-wysiwyg-emoji-category';
            
            // Create category title
            const categoryTitle = document.createElement('div');
            categoryTitle.className = 'my-wysiwyg-emoji-title';
            categoryTitle.innerText = categoryName;
            categorySection.appendChild(categoryTitle);

            // Create grid for emojis within this category
            const grid = document.createElement('div');
            grid.className = 'my-wysiwyg-emoji-grid';

            // Populate grid with buttons
            emojiArray.forEach(emoji => {
                const btn = document.createElement('button');
                btn.className = 'my-wysiwyg-emoji-btn';
                btn.type = 'button';
                btn.innerHTML = emoji;
                btn.title = emoji; // Hover accessibility
                
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this._restoreSelection();
                    document.execCommand('insertText', false, emoji);
                    this.emojiPicker.classList.remove('show');
                    this._handleInput();
                });
                
                grid.appendChild(btn);
            });

            categorySection.appendChild(grid);
            this.emojiPicker.appendChild(categorySection);
        }
    }

    /**
     * Toggles and dynamically positions the emoji picker
     * @param {HTMLElement} btn 
     */
    _toggleEmojiPicker(btn) {
        const isShowing = this.emojiPicker.classList.contains('show');
        
        if (isShowing) {
            this.emojiPicker.classList.remove('show');
        } else {
            this.emojiPicker.classList.add('show');
            this._saveSelection();

            // Calculate position relative to the main container
            const containerRect = this.container.getBoundingClientRect();
            const btnRect = btn.getBoundingClientRect();

            // Position immediately below the button
            let topPos = (btnRect.bottom - containerRect.top) + 4; // 4px offset
            let leftPos = (btnRect.left - containerRect.left);

            // Prevent picker from overflowing the right side of the container
            if (leftPos + 220 > containerRect.width) { // 220px is picker width
                leftPos = containerRect.width - 230;
            }

            this.emojiPicker.style.top = `${topPos}px`;
            this.emojiPicker.style.left = `${leftPos}px`;
        }
    }

    _buildEditor() {
        this.editor = document.createElement('div');
        this.editor.className = 'my-wysiwyg-editor';
        this.editor.setAttribute('contenteditable', 'true');
        this.editor.style.minHeight = this.options.minHeight;
        this.editor.style.maxHeight = this.options.maxHeight;
        // Set the placeholder attribute for CSS to read
        if (this.options.placeholder) {
            this.editor.setAttribute('data-placeholder', this.options.placeholder);
        }
        this.editor.addEventListener('input', () => this._handleInput());
        this.editor.addEventListener('blur', () => this._syncTextarea());
        
        this.editor.addEventListener('click', () => this._checkLinkInteraction());
        this.editor.addEventListener('keyup', (e) => {
            if (e.key.startsWith('Arrow')) this._checkLinkInteraction();
        });
        this.editor.addEventListener('keydown', (e) => this._handleKeydown(e));
        this.editor.addEventListener('paste', (e) => this._handlePaste(e));
    }

    _buildStatusBar() {
        this.statusbar = document.createElement('div');
        this.statusbar.className = 'my-wysiwyg-statusbar';
        this.charCounter = document.createElement('span');
        this.statusbar.appendChild(this.charCounter);
    }

    /**
     * Blocks typing if the character limit is reached,
     * accounting for control keys and text selection replacements.
     * @param {KeyboardEvent} e 
     */
    _handleKeydown(e) {
        if (this.options.maxChars === Infinity) return;

        // Allow navigation, deletion, and standard control keys
        const allowedKeys = [
            'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
            'Home', 'End', 'Tab'
        ];

        // Allow system shortcuts (Ctrl+A, Ctrl+C, Ctrl+Z, etc.)
        if (allowedKeys.includes(e.key) || e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }

        const textContent = this.editor.innerText || this.editor.textContent;
        const selectedTextLength = window.getSelection().toString().length;

        // If current length (minus any text we are about to overwrite) 
        // is at or above the limit, block the keypress completely
        if (textContent.length - selectedTextLength >= this.options.maxChars) {
            e.preventDefault();
        }
    }

    /**
     * Intercepts copy/paste events to dynamically truncate incoming 
     * text if it exceeds the maximum character limit.
     * @param {ClipboardEvent} e 
     */
    _handlePaste(e) {
        if (this.options.maxChars === Infinity) return;

        // Retrieve incoming plain text
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        const textContent = this.editor.innerText || this.editor.textContent;
        const selectedTextLength = window.getSelection().toString().length;
        
        // Calculate how many characters we actually have stored
        const currentLength = textContent.length - selectedTextLength;
        
        // If the paste operation would break the limit
        if (currentLength + pastedText.length > this.options.maxChars) {
            e.preventDefault(); // Stop native pasting mechanism
            
            const availableSpace = this.options.maxChars - currentLength;
            
            if (availableSpace > 0) {
                // Slice the string and insert only what fits safely
                const textToInsert = pastedText.substring(0, availableSpace);
                document.execCommand('insertText', false, textToInsert);
            }
            
            // Manually trigger UI and data sync
            this._handleInput();
        }
    }

    /**
     * Evaluates if the editor is empty (ignoring invisible browser-generated tags)
     * and triggers the CSS placeholder
     */
    _checkEmptyState() {
        const html = this.editor.innerHTML.trim();
        // Browsers often leave a <br> or empty <p> when content is deleted
        const isEmpty = html === '' || html === '<br>' || html === '<p><br></p>' || html === '<p></p>';
        
        if (isEmpty) {
            this.editor.setAttribute('data-empty', 'true');
        } else {
            this.editor.removeAttribute('data-empty');
        }
    }

    /* --- TOOLTIPS --- */

    _buildLinkTooltip() {
        this.linkTooltip = document.createElement('div');
        this.linkTooltip.className = 'my-wysiwyg-link-tooltip';

        const editBtn = document.createElement('span');
        editBtn.innerHTML = `<svg viewBox="0 0 24 24" style="width:14px;height:14px;display:block;stroke:currentColor;fill:none;stroke-width:2;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>`;
        editBtn.title = this.i18n.editLink;
        editBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this._editActiveLink();
        });

        const removeBtn = document.createElement('span');
        removeBtn.className = 'remove-link';
        removeBtn.innerHTML = `<svg viewBox="0 0 24 24" style="width:14px;height:14px;display:block;stroke:currentColor;fill:none;stroke-width:2;"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>`;
        removeBtn.title = this.i18n.removeLink;
        removeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this._removeActiveLink();
        });

        this.linkTooltip.appendChild(editBtn);
        this.linkTooltip.appendChild(removeBtn);
    }

    _checkLinkInteraction() {
        const selection = window.getSelection();
        if (!selection.rangeCount) return;
        let node = selection.anchorNode;
        if (node && node.nodeType === 3) node = node.parentNode;
        const anchor = node ? node.closest('a') : null;

        if (anchor && this.editor.contains(anchor)) {
            this._showLinkTooltip(anchor);
        } else {
            this._hideLinkTooltip();
        }
    }

    _showLinkTooltip(anchorElement) {
        this.activeLinkNode = anchorElement;
        this.linkTooltip.classList.add('show');
        const containerRect = this.container.getBoundingClientRect();
        const anchorRect = anchorElement.getBoundingClientRect();
        let topPos = (anchorRect.top - containerRect.top) + this.editor.scrollTop - 40;
        let leftPos = (anchorRect.left - containerRect.left);

        if (topPos < 0) topPos = (anchorRect.bottom - containerRect.top) + this.editor.scrollTop + 5;
        if (leftPos < 0) leftPos = 10;

        this.linkTooltip.style.top = `${topPos}px`;
        this.linkTooltip.style.left = `${leftPos}px`;
    }

    _hideLinkTooltip() {
        this.activeLinkNode = null;
        this.linkTooltip.classList.remove('show');
    }

    _editActiveLink() {
        if (!this.activeLinkNode) return;
        const newUrl = prompt(this.i18n.link + ' URL:', this.activeLinkNode.href);
        if (newUrl !== null) {
            this.activeLinkNode.href = this._sanitizeUrl(newUrl);
            this._handleInput();
        }
        this._hideLinkTooltip();
    }

    _removeActiveLink() {
        if (!this.activeLinkNode) return;
        const textNode = document.createTextNode(this.activeLinkNode.textContent);
        this.activeLinkNode.parentNode.replaceChild(textNode, this.activeLinkNode);
        this._hideLinkTooltip();
        this._handleInput();
    }

    /* --- SECURITY & EXECUTION --- */

    _sanitizeUrl(url) {
        if (!url) return null;
        if (/^(javascript|vbscript|data):/i.test(url.trim())) return null;
        return url;
    }

    _escapeHTML(str) {
        return str.replace(/[&<>'"]/g, tag => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        }[tag] || tag));
    }

    _executeCommand(command, value = null) {
        if (this.isHtmlView && command !== 'html') return; 

        let url, htmlToInsert;

        switch (command) {
            case 'html':
                this._toggleHtmlView();
                break;
            case 'link':
                url = this._sanitizeUrl(prompt(this.i18n.link + ' URL:'));
                if (url) document.execCommand('createLink', false, url);
                break;
            case 'image':
                url = this._sanitizeUrl(prompt(this.i18n.image + ' URL:'));
                if (url) document.execCommand('insertImage', false, url);
                break;
            case 'video':
                url = this._sanitizeUrl(prompt(this.i18n.video + ' (YouTube Embed URL):'));
                if (url) {
                    htmlToInsert = `<iframe width="560" height="315" src="${url}" frameborder="0" allowfullscreen></iframe><br/>`;
                    document.execCommand('insertHTML', false, htmlToInsert);
                }
                break;
            case 'code':
                const selection = window.getSelection().toString();
                const safeCode = this._escapeHTML(selection || 'Code here...');
                htmlToInsert = `<pre><code>${safeCode}</code></pre><br/>`;
                document.execCommand('insertHTML', false, htmlToInsert);
                break;
            case 'hr':
                document.execCommand('insertHorizontalRule', false, null);
                break;
            // Native formats that might require a value
            case 'fontName':
            case 'fontSize':
            case 'foreColor':
                document.execCommand(command, false, value);
                break;
            // Alignments
            case 'alignLeft': document.execCommand('justifyLeft', false, null); break;
            case 'alignCenter': document.execCommand('justifyCenter', false, null); break;
            case 'alignRight': document.execCommand('justifyRight', false, null); break;
            case 'alignJustify': document.execCommand('justifyFull', false, null); break;
            case 'ul': document.execCommand('insertUnorderedList', false, null); break;
            case 'ol': document.execCommand('insertOrderedList', false, null); break;
            default:
                document.execCommand(command, false, null);
        }
        
        this._handleInput();
        if (command !== 'fontName' && command !== 'fontSize' && command !== 'foreColor') {
            this.editor.focus();
        }
    }

    _saveSelection() {
        if (window.getSelection && window.getSelection().rangeCount > 0) {
            this.savedSelection = window.getSelection().getRangeAt(0);
        }
    }

    _restoreSelection() {
        if (this.savedSelection && window.getSelection) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(this.savedSelection);
        } else {
            this.editor.focus();
        }
    }

    _toggleHtmlView() {
        this.isHtmlView = !this.isHtmlView;
        if (this.isHtmlView) {
            this.editor.textContent = this.editor.innerHTML;
            this.editor.style.fontFamily = 'monospace';
            this.editor.style.whiteSpace = 'pre-wrap';
        } else {
            this.editor.innerHTML = this.editor.textContent;
            this.editor.style.fontFamily = '';
            this.editor.style.whiteSpace = 'normal';
        }
    }

    _handleInput() {
        let currentLength = (this.editor.innerText || this.editor.textContent).length;
        if (this.options.maxChars !== Infinity && currentLength > this.options.maxChars) {
            this.statusbar.classList.add('limit-reached');
        } else {
            this.statusbar.classList.remove('limit-reached');
        }
        this._updateCharCount(currentLength);
        this._syncTextarea();
        this._checkEmptyState();
    }

    _syncTextarea() {
        this.textarea.value = this.isHtmlView ? this.editor.innerText : this.editor.innerHTML;
    }

    _updateCharCount(length = null) {
        let currentLength = length !== null ? length : (this.editor.innerText || this.editor.textContent).length;
        let limitText = this.options.maxChars === Infinity ? '&infin;' : this.options.maxChars;
        this.charCounter.innerHTML = `${currentLength} / ${limitText} ${(this.i18n.chars || 'Chars')}`;
    }

    _getIconForAction(action) {
        const svgs = {
            bold: '<svg viewBox="0 0 24 24"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path></svg>',
            italic: '<svg viewBox="0 0 24 24"><line x1="19" y1="4" x2="10" y2="4"></line><line x1="14" y1="20" x2="5" y2="20"></line><line x1="15" y1="4" x2="9" y2="20"></line></svg>',
            underline: '<svg viewBox="0 0 24 24"><path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"></path><line x1="4" y1="21" x2="20" y2="21"></line></svg>',
            alignLeft: '<svg viewBox="0 0 24 24"><line x1="17" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg>',
            alignCenter: '<svg viewBox="0 0 24 24"><line x1="18" y1="10" x2="6" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="18" y1="18" x2="6" y2="18"></line></svg>',
            alignRight: '<svg viewBox="0 0 24 24"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg>',
            alignJustify: '<svg viewBox="0 0 24 24"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg>',
            ul: '<svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>',
            ol: '<svg viewBox="0 0 24 24"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></line><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>',
            link: '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>',
            image: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>',
            video: '<svg viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>',
            emoji: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>',
            code: '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><polyline points="10 13 8 15 10 17"></polyline><polyline points="14 13 16 15 14 17"></polyline></svg>',
            hr: '<svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"></line></svg>',
            undo: '<svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>',
            redo: '<svg viewBox="0 0 24 24"><polyline points="15 14 20 9 15 4"></polyline><path d="M4 20v-7a4 4 0 0 1 4-4h12"></path></svg>',
            html: '<svg viewBox="0 0 24 24"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>'
        };
        return svgs[action] || action;
    }

    /**
     * Programmatically sets the HTML content of the editor.
     * Use an empty string '' to clear the editor completely.
     * @param {string} html - The HTML content to insert
     */
    setHtml(html) {
        // Set the content in the visual editor div
        this.editor.innerHTML = html;

        // Re-trigger the input handler to automatically sync the hidden textarea,
        // update the character counters, and toggle the empty state placeholder
        this._handleInput();
    }
}
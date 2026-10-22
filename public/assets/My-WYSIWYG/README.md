# My-WYSIWYG
A lightweight, modern, and zero-dependency Vanilla JavaScript WYSIWYG (What You See Is What You Get) text editor. 

Designed to seamlessly replace standard HTML `<textarea>` elements, **My-WYSIWYG** provides a rich text editing experience with a fully responsive toolbar, inline SVG icons, dynamic character limits, and smart positioning for dropdowns.

## ✨ Features

* **Zero Dependencies:** No jQuery, no FontAwesome, no heavy frameworks. Pure Vanilla JS and CSS.
* **Responsive Toolbar:** Gracefully collapses into a single line, hiding overflowing tools behind an expandable toggle.
* **Smart Dropdowns:** Tooltips and emoji pickers dynamically calculate their position to avoid getting clipped by container borders.
* **Hard Character Limits:** Intercepts both typing (`keydown`) and `paste` events to enforce strict character limits without breaking HTML tags.
* **Frontend Security:** Built-in sanitization for malicious URL protocols (`javascript:`, `data:`) and HTML entity escaping for code blocks.
* **i18n Ready:** Easily translatable through a dedicated dictionary file.
* **Native Inputs:** Utilizes the browser's native Color Picker for a footprint-free experience.

## 🚀 Installation

Include the CSS and JS files in your HTML document. 

```html
<head>
    <link rel="stylesheet" href="css/my_wysiwyg.css">
</head>
<body>
    <textarea id="editor" name="content" placeholder="Type your text here..."></textarea>

    <script src="js/my_wysiwyg_i18n.js"></script>
    <script src="js/my_wysiwyg.js"></script>
</body>

```

## 🛠️ Usage & Initialization

Initialize the plugin by targeting your `<textarea>` selector and passing an optional configuration object.

### Basic Example

```javascript
document.addEventListener('DOMContentLoaded', () => {
    // Initializes with default English settings and standard toolbar
    const editor = new MyWysiwyg('#editor');
});

```

### Advanced Configuration Example

```javascript
document.addEventListener('DOMContentLoaded', () => {
    const editor = new MyWysiwyg('#editor', {
        lang: 'it',                  // Use Italian dictionary
        maxChars: 1000,              // Hard limit of 1000 characters
        minHeight: '200px',          // Starting height
        maxHeight: '600px',          // Max height before scrollbars appear
        placeholder: 'Scrivi...',    // Overrides the HTML attribute
        // Customize the toolbar layout. Use '|' for visual separators.
        toolbar: [
            'fontName', 'fontSize', 'foreColor', '|',
            'bold', 'italic', 'underline', '|', 
            'alignLeft', 'alignCenter', 'alignRight', 'alignJustify', '|',
            'ul', 'ol', '|', 
            'link', 'image', 'video', 'emoji', '|', 
            'code', 'hr', '|', 
            'undo', 'redo'
        ]
    });
});

```

## ⚙️ Configuration Options

| Option | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `lang` | `string` | `'en'` | The language key matching the `MyWysiwygI18n` dictionary. |
| `maxChars` | `number` | `Infinity` | The strict character limit. Blocks typing and truncates pasted text. |
| `minHeight` | `string` | `'150px'` | Minimum CSS height of the editable area. |
| `maxHeight` | `string` | `'400px'` | Maximum CSS height before internal scrolling activates. |
| `placeholder` | `string` | *(Native attr)* | Placeholder text. Defaults to the `<textarea>`'s native attribute. |
| `toolbar` | `array` | *(Full array)* | Array of strings defining the active buttons. **See Toolbar Options below.** |
| `emojis` | `array` | *(12 emojis)* | Array of emoji strings available in the dropdown picker. |

### 🧰 Toolbar Options

Below are all the available string values you can pass into the `toolbar` array to customize your editor's interface. 

| Category | Available Values |
| :--- | :--- |
| **Fonts & Color** | `'fontName'`, `'fontSize'`, `'foreColor'` |
| **Text Style** | `'bold'`, `'italic'`, `'underline'` |
| **Alignment** | `'alignLeft'`, `'alignCenter'`, `'alignRight'`, `'alignJustify'` |
| **Lists** | `'ul'` (Unordered/Bullets), `'ol'` (Ordered/Numbers) |
| **Inserts** | `'link'`, `'image'`, `'video'`, `'emoji'`, `'code'`, `'hr'` (Horizontal Line) |
| **Actions** | `'undo'`, `'redo'`, `'html'` (Toggle Source Code) |
| **UI Elements** | <code>'&#124;'</code> (Creates a vertical visual separator) |

## 🔌 Developer API & Events

My-WYSIWYG is designed to be developer-friendly. It creates a dynamic `contenteditable` container (`.my-wysiwyg-editor`) and synchronizes its content back to your original `<textarea>`. 

Here is how you can interact with the editor programmatically.

### Write html programmatically in the textarea
You can interact with the editor instance programmatically using built-in methods.
```javascript
const editor = new MyWysiwyg('#editor');

// Set or clear the editor's content programmatically
// This automatically updates character counts and the hidden textarea
editor.setHtml(''); 
editor.setHtml('<p>New <b>content</b>!</p>');
```

### Listening to Real-Time Changes (Events)
If you need to trigger actions while the user is typing (e.g., for live character counters, real-time previews, or auto-saving), you should listen to the `input` event delegated to the `.my-wysiwyg-editor` class.

**Using Vanilla JavaScript:**
```javascript
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('my-wysiwyg-editor')) {
        // Extract plain text (strips out all HTML tags)
        let plainText = e.target.innerText || e.target.textContent;
        
        // Extract raw HTML formatted content
        let htmlContent = e.target.innerHTML;
        
        console.log('User is typing. Current text length:', plainText.length);
    }
});

```

**Using jQuery:**

```javascript
$(document).on('input', '.my-wysiwyg-editor', function() {
    let plainText = $(this).text();
    let htmlContent = $(this).html();
    
    console.log('Real-time text:', plainText);
});

```

### Retrieving the Data

You do not need to call any custom methods to extract the data for your backend. The plugin operates as a "puppeteer", automatically syncing the visual HTML back to your original hidden `<textarea>`'s `value` attribute on blur or form submission.

```javascript
// Simply grab the value of your original element as you normally would!
let finalHtmlToSubmit = document.querySelector('#editor').value;

// Or in jQuery: 
// let finalHtmlToSubmit = $('#editor').val();

```

## 🛡️ Security Guidelines (Crucial)

**My-WYSIWYG** implements frontend safeguards (like blocking `javascript:` URIs in links and escaping HTML inside `<pre><code>` blocks) to prevent accidental Self-XSS.

However, **client-side security is never enough**. Because this editor submits raw HTML to your backend, a malicious user could bypass the JavaScript frontend entirely and send a POST request containing XSS payloads (e.g., `<img src=x onerror=alert(1)>`).

### Backend Sanitization (PHP Example)

You **must** sanitize the incoming HTML on your server before storing it in a database or rendering it to other users. Do not use simple blacklist approaches like `strip_tags()`.

We strongly recommend using **[HTML Purifier](https://www.google.com/search?q=http://htmlpurifier.org/)** for a strict whitelisting approach:

```php
// 1. Include HTML Purifier
require_once '/path/to/HTMLPurifier.auto.php';

$config = HTMLPurifier_Config::createDefault();
$config->set('Core.Encoding', 'UTF-8');

// 2. Define strictly allowed tags and attributes (Whitelisting)
$config->set('HTML.Allowed', 'p[style|align],b,strong,i,em,u,a[href|title|target],ul,ol,li,br,span[style],pre,code,hr,img[src|alt|width|height|style],iframe[width|height|src|frameborder|allowfullscreen],font[face|size|color]');

// 3. Allow IFrames ONLY from trusted sources (e.g., YouTube embedded videos)
$config->set('HTML.SafeIframe', true);
$config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

// 4. Force safe protocols for links
$config->set('URI.AllowedSchemes', array('http' => true, 'https' => true, 'mailto' => true));

$purifier = new HTMLPurifier($config);

// 5. Clean the raw POST data securely
$safe_html = $purifier->purify($_POST['content']);

```

## 🌍 Adding Languages (i18n)

To add a new language, simply expand the `MyWysiwygI18n` object in the `my_wysiwyg_i18n.js` file:

```javascript
const MyWysiwygI18n = {
    en: { /* English strings */ },
    it: { /* Italian strings */ },
    es: {
        bold: 'Negrita',
        italic: 'Cursiva',
        // ... translate remaining keys
    }
};

```

## 📄 License

This project is licensed under the MIT License.

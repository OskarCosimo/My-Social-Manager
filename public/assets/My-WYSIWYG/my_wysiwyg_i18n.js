/**
 * my_wysiwyg_i18n.js
 * i18n dictionary for My WYSIWYG
 * Supports 20 Languages
 */
const MyWysiwygI18n = {
    // 1. English
    en: {
        fontName: 'Font Family', fontSize: 'Font Size', foreColor: 'Text Color',
        bold: 'Bold', italic: 'Italic', underline: 'Underline',
        alignLeft: 'Align Left', alignCenter: 'Align Center', alignRight: 'Align Right', alignJustify: 'Justify',
        ul: 'Unordered List', ol: 'Ordered List',
        link: 'Insert Link', editLink: 'Edit Link', removeLink: 'Unlink',
        image: 'Insert Image', video: 'Insert Video', emoji: 'Emoji',
        code: 'Insert Code Block', hr: 'Horizontal Rule',
        undo: 'Undo', redo: 'Redo', html: 'Source Code (HTML)',
        expand: 'Toggle Toolbar', chars: 'Characters', limitReached: 'Character limit reached!'
    },
    // 2. Italian
    it: {
        fontName: 'Tipo di Carattere', fontSize: 'Dimensione Testo', foreColor: 'Colore Testo',
        bold: 'Grassetto', italic: 'Corsivo', underline: 'Sottolineato',
        alignLeft: 'Allinea a Sinistra', alignCenter: 'Allinea al Centro', alignRight: 'Allinea a Destra', alignJustify: 'Giustifica',
        ul: 'Elenco Puntato', ol: 'Elenco Numerato',
        link: 'Inserisci Link', editLink: 'Modifica Link', removeLink: 'Rimuovi Link',
        image: 'Inserisci Immagine', video: 'Inserisci Video', emoji: 'Emoji',
        code: 'Inserisci Blocco Codice', hr: 'Linea Orizzontale',
        undo: 'Annulla', redo: 'Ripeti', html: 'Sorgente (HTML)',
        expand: 'Espandi Barra', chars: 'Caratteri', limitReached: 'Limite caratteri raggiunto!'
    },
    // 3. Spanish
    es: {
        fontName: 'Fuente', fontSize: 'Tamaño de fuente', foreColor: 'Color del texto',
        bold: 'Negrita', italic: 'Cursiva', underline: 'Subrayado',
        alignLeft: 'Alinear a la izquierda', alignCenter: 'Centrar', alignRight: 'Alinear a la derecha', alignJustify: 'Justificar',
        ul: 'Lista de viñetas', ol: 'Lista numerada',
        link: 'Insertar enlace', editLink: 'Editar enlace', removeLink: 'Quitar enlace',
        image: 'Insertar imagen', video: 'Insertar video', emoji: 'Emoji',
        code: 'Insertar código', hr: 'Línea horizontal',
        undo: 'Deshacer', redo: 'Rehacer', html: 'Código fuente (HTML)',
        expand: 'Expandir barra', chars: 'Caracteres', limitReached: '¡Límite de caracteres alcanzado!'
    },
    // 4. French
    fr: {
        fontName: 'Police', fontSize: 'Taille de police', foreColor: 'Couleur du texte',
        bold: 'Gras', italic: 'Italique', underline: 'Souligné',
        alignLeft: 'Aligner à gauche', alignCenter: 'Centrer', alignRight: 'Aligner à droite', alignJustify: 'Justifier',
        ul: 'Liste à puces', ol: 'Liste numérotée',
        link: 'Insérer un lien', editLink: 'Modifier le lien', removeLink: 'Supprimer le lien',
        image: 'Insérer une image', video: 'Insérer une vidéo', emoji: 'Emoji',
        code: 'Insérer un bloc de code', hr: 'Ligne horizontale',
        undo: 'Annuler', redo: 'Rétablir', html: 'Code source (HTML)',
        expand: 'Développer la barre', chars: 'Caractères', limitReached: 'Limite de caractères atteinte !'
    },
    // 5. German
    de: {
        fontName: 'Schriftart', fontSize: 'Schriftgröße', foreColor: 'Textfarbe',
        bold: 'Fett', italic: 'Kursiv', underline: 'Unterstrichen',
        alignLeft: 'Linksbündig', alignCenter: 'Zentriert', alignRight: 'Rechtsbündig', alignJustify: 'Blocksatz',
        ul: 'Aufzählungszeichen', ol: 'Nummerierte Liste',
        link: 'Link einfügen', editLink: 'Link bearbeiten', removeLink: 'Link entfernen',
        image: 'Bild einfügen', video: 'Video einfügen', emoji: 'Emoji',
        code: 'Codeblock einfügen', hr: 'Horizontale Linie',
        undo: 'Rückgängig', redo: 'Wiederholen', html: 'Quellcode (HTML)',
        expand: 'Symbolleiste erweitern', chars: 'Zeichen', limitReached: 'Zeichenlimit erreicht!'
    },
    // 6. Portuguese
    pt: {
        fontName: 'Fonte', fontSize: 'Tamanho da fonte', foreColor: 'Cor do texto',
        bold: 'Negrito', italic: 'Itálico', underline: 'Sublinhado',
        alignLeft: 'Alinhar à esquerda', alignCenter: 'Centralizar', alignRight: 'Alinhar à direita', alignJustify: 'Justificar',
        ul: 'Lista de marcadores', ol: 'Lista numerada',
        link: 'Inserir link', editLink: 'Editar link', removeLink: 'Remover link',
        image: 'Inserir imagem', video: 'Inserir vídeo', emoji: 'Emoji',
        code: 'Inserir bloco de código', hr: 'Linha horizontal',
        undo: 'Desfazer', redo: 'Refazer', html: 'Código-fonte (HTML)',
        expand: 'Expandir barra', chars: 'Caracteres', limitReached: 'Limite de caracteres atingido!'
    },
    // 7. Russian
    ru: {
        fontName: 'Шрифт', fontSize: 'Размер шрифта', foreColor: 'Цвет текста',
        bold: 'Жирный', italic: 'Курсив', underline: 'Подчеркнутый',
        alignLeft: 'По левому краю', alignCenter: 'По центру', alignRight: 'По правому краю', alignJustify: 'По ширине',
        ul: 'Маркированный список', ol: 'Нумерованный список',
        link: 'Вставить ссылку', editLink: 'Редактировать ссылку', removeLink: 'Удалить ссылку',
        image: 'Вставить изображение', video: 'Вставить видео', emoji: 'Эмодзи',
        code: 'Вставить код', hr: 'Горизонтальная линия',
        undo: 'Отменить', redo: 'Повторить', html: 'Исходный код (HTML)',
        expand: 'Развернуть панель', chars: 'Символы', limitReached: 'Достигнут лимит символов!'
    },
    // 8. Chinese (Simplified)
    zh: {
        fontName: '字体', fontSize: '字体大小', foreColor: '文本颜色',
        bold: '粗体', italic: '斜体', underline: '下划线',
        alignLeft: '左对齐', alignCenter: '居中', alignRight: '右对齐', alignJustify: '两端对齐',
        ul: '无序列表', ol: '有序列表',
        link: '插入链接', editLink: '编辑链接', removeLink: '取消链接',
        image: '插入图片', video: '插入视频', emoji: '表情符号',
        code: '插入代码块', hr: '水平线',
        undo: '撤销', redo: '重做', html: '源代码 (HTML)',
        expand: '展开工具栏', chars: '字符', limitReached: '达到字符限制！'
    },
    // 9. Japanese
    ja: {
        fontName: 'フォント', fontSize: 'フォントサイズ', foreColor: 'テキストの色',
        bold: '太字', italic: '斜体', underline: '下線',
        alignLeft: '左揃え', alignCenter: '中央揃え', alignRight: '右揃え', alignJustify: '両端揃え',
        ul: '箇条書き', ol: '番号付きリスト',
        link: 'リンクを挿入', editLink: 'リンクを編集', removeLink: 'リンクを解除',
        image: '画像を挿入', video: '動画を挿入', emoji: '絵文字',
        code: 'コードブロックを挿入', hr: '水平線',
        undo: '元に戻す', redo: 'やり直し', html: 'ソースコード (HTML)',
        expand: 'ツールバーを展開', chars: '文字', limitReached: '文字数制限に達しました！'
    },
    // 10. Arabic
    ar: {
        fontName: 'الخط', fontSize: 'حجم الخط', foreColor: 'لون النص',
        bold: 'غامق', italic: 'مائل', underline: 'تسطير',
        alignLeft: 'محاذاة لليسار', alignCenter: 'توسيط', alignRight: 'محاذاة لليمين', alignJustify: 'ضبط',
        ul: 'قائمة نقطية', ol: 'قائمة رقمية',
        link: 'إدراج رابط', editLink: 'تحرير الرابط', removeLink: 'إزالة الرابط',
        image: 'إدراج صورة', video: 'إدراج فيديو', emoji: 'رموز تعبيرية',
        code: 'إدراج كود', hr: 'خط أفقي',
        undo: 'تراجع', redo: 'إعادة', html: 'شفرة المصدر (HTML)',
        expand: 'توسيع شريط الأدوات', chars: 'أحرف', limitReached: 'تم الوصول إلى الحد الأقصى للأحرف!'
    },
    // 11. Hindi
    hi: {
        fontName: 'फ़ॉन्ट', fontSize: 'फ़ॉन्ट का आकार', foreColor: 'पाठ का रंग',
        bold: 'बोल्ड', italic: 'इटैलिक', underline: 'अंडरलाइन',
        alignLeft: 'बाएँ संरेखित करें', alignCenter: 'केंद्र', alignRight: 'दाएँ संरेखित करें', alignJustify: 'जस्टिफ़ाई',
        ul: 'बुलेट सूची', ol: 'क्रमांकित सूची',
        link: 'लिंक डालें', editLink: 'लिंक संपादित करें', removeLink: 'लिंक हटाएं',
        image: 'छवि डालें', video: 'वीडियो डालें', emoji: 'इमोजी',
        code: 'कोड ब्लॉक डालें', hr: 'क्षैतिज रेखा',
        undo: 'पूर्ववत करें', redo: 'फिर से करें', html: 'स्रोत कोड (HTML)',
        expand: 'टूलबार का विस्तार करें', chars: 'वर्ण', limitReached: 'वर्ण सीमा पार हो गई!'
    },
    // 12. Korean
    ko: {
        fontName: '글꼴', fontSize: '글꼴 크기', foreColor: '텍스트 색상',
        bold: '굵게', italic: '기울임꼴', underline: '밑줄',
        alignLeft: '왼쪽 정렬', alignCenter: '가운데 정렬', alignRight: '오른쪽 정렬', alignJustify: '양쪽 맞춤',
        ul: '글머리 기호', ol: '번호 매기기',
        link: '링크 삽입', editLink: '링크 수정', removeLink: '링크 제거',
        image: '이미지 삽입', video: '동영상 삽입', emoji: '이모티콘',
        code: '코드 블록 삽입', hr: '가로줄',
        undo: '실행 취소', redo: '다시 실행', html: '소스 코드 (HTML)',
        expand: '도구 모음 확장', chars: '문자', limitReached: '문자 제한에 도달했습니다!'
    },
    // 13. Turkish
    tr: {
        fontName: 'Yazı Tipi', fontSize: 'Boyut', foreColor: 'Metin Rengi',
        bold: 'Kalın', italic: 'İtalik', underline: 'Altı Çizili',
        alignLeft: 'Sola Hizala', alignCenter: 'Ortala', alignRight: 'Sağa Hizala', alignJustify: 'İki Yana Yasla',
        ul: 'Madde İşaretleri', ol: 'Numaralandırma',
        link: 'Bağlantı Ekle', editLink: 'Bağlantıyı Düzenle', removeLink: 'Bağlantıyı Kaldır',
        image: 'Resim Ekle', video: 'Video Ekle', emoji: 'Emoji',
        code: 'Kod Ekle', hr: 'Yatay Çizgi',
        undo: 'Geri Al', redo: 'Yinele', html: 'Kaynak Kodu (HTML)',
        expand: 'Araç Çubuğunu Genişlet', chars: 'Karakter', limitReached: 'Karakter sınırına ulaşıldı!'
    },
    // 14. Dutch
    nl: {
        fontName: 'Lettertype', fontSize: 'Tekengrootte', foreColor: 'Tekstkleur',
        bold: 'Vet', italic: 'Cursief', underline: 'Onderstreept',
        alignLeft: 'Links uitlijnen', alignCenter: 'Centreren', alignRight: 'Rechts uitlijnen', alignJustify: 'Uitvullen',
        ul: 'Opsommingstekens', ol: 'Genummerde lijst',
        link: 'Link invoegen', editLink: 'Link bewerken', removeLink: 'Link verwijderen',
        image: 'Afbeelding invoegen', video: 'Video invoegen', emoji: 'Emoji',
        code: 'Codeblok invoegen', hr: 'Horizontale lijn',
        undo: 'Ongedaan maken', redo: 'Opnieuw uitvoeren', html: 'Broncode (HTML)',
        expand: 'Werkbalk uitvouwen', chars: 'Tekens', limitReached: 'Tekenlimiet bereikt!'
    },
    // 15. Polish
    pl: {
        fontName: 'Czcionka', fontSize: 'Rozmiar czcionki', foreColor: 'Kolor tekstu',
        bold: 'Pogrubienie', italic: 'Kursywa', underline: 'Podkreślenie',
        alignLeft: 'Wyrównaj do lewej', alignCenter: 'Wyśrodkuj', alignRight: 'Wyrównaj do prawej', alignJustify: 'Wyjustuj',
        ul: 'Lista wypunktowana', ol: 'Lista numerowana',
        link: 'Wstaw link', editLink: 'Edytuj link', removeLink: 'Usuń link',
        image: 'Wstaw obraz', video: 'Wstaw wideo', emoji: 'Emoji',
        code: 'Wstaw kod', hr: 'Linia pozioma',
        undo: 'Cofnij', redo: 'Ponów', html: 'Kod źródłowy (HTML)',
        expand: 'Rozwiń pasek', chars: 'Znaki', limitReached: 'Osiągnięto limit znaków!'
    },
    // 16. Indonesian
    id: {
        fontName: 'Font', fontSize: 'Ukuran Font', foreColor: 'Warna Teks',
        bold: 'Tebal', italic: 'Miring', underline: 'Garis Bawah',
        alignLeft: 'Rata Kiri', alignCenter: 'Rata Tengah', alignRight: 'Rata Kanan', alignJustify: 'Rata Kiri Kanan',
        ul: 'Daftar Tak Berurutan', ol: 'Daftar Berurutan',
        link: 'Sisipkan Tautan', editLink: 'Edit Tautan', removeLink: 'Hapus Tautan',
        image: 'Sisipkan Gambar', video: 'Sisipkan Video', emoji: 'Emoji',
        code: 'Sisipkan Kode', hr: 'Garis Horizontal',
        undo: 'Batal', redo: 'Ulangi', html: 'Kode Sumber (HTML)',
        expand: 'Perluas Bilah Alat', chars: 'Karakter', limitReached: 'Batas karakter tercapai!'
    },
    // 17. Vietnamese
    vi: {
        fontName: 'Phông chữ', fontSize: 'Cỡ chữ', foreColor: 'Màu chữ',
        bold: 'In đậm', italic: 'In nghiêng', underline: 'Gạch chân',
        alignLeft: 'Căn trái', alignCenter: 'Căn giữa', alignRight: 'Căn phải', alignJustify: 'Căn đều',
        ul: 'Danh sách dấu đầu dòng', ol: 'Danh sách được đánh số',
        link: 'Chèn liên kết', editLink: 'Sửa liên kết', removeLink: 'Xóa liên kết',
        image: 'Chèn hình ảnh', video: 'Chèn video', emoji: 'Biểu tượng cảm xúc',
        code: 'Chèn mã', hr: 'Đường ngang',
        undo: 'Hoàn tác', redo: 'Làm lại', html: 'Mã nguồn (HTML)',
        expand: 'Mở rộng thanh công cụ', chars: 'Ký tự', limitReached: 'Đã đạt giới hạn ký tự!'
    },
    // 18. Thai
    th: {
        fontName: 'แบบอักษร', fontSize: 'ขนาดอักษร', foreColor: 'สีข้อความ',
        bold: 'ตัวหนา', italic: 'ตัวเอียง', underline: 'ขีดเส้นใต้',
        alignLeft: 'จัดชิดซ้าย', alignCenter: 'จัดกึ่งกลาง', alignRight: 'จัดชิดขวา', alignJustify: 'จัดเต็มขอบ',
        ul: 'รายการสัญลักษณ์', ol: 'รายการตัวเลข',
        link: 'แทรกลิงก์', editLink: 'แก้ไขลิงก์', removeLink: 'เอาลิงก์ออก',
        image: 'แทรกรูปภาพ', video: 'แทรกวิดีโอ', emoji: 'อีโมจิ',
        code: 'แทรกโค้ด', hr: 'เส้นแนวนอน',
        undo: 'เลิกทำ', redo: 'ทำซ้ำ', html: 'ซอร์สโค้ด (HTML)',
        expand: 'ขยายแถบเครื่องมือ', chars: 'ตัวอักษร', limitReached: 'ถึงขีดจำกัดตัวอักษรแล้ว!'
    },
    // 19. Greek
    el: {
        fontName: 'Γραμματοσειρά', fontSize: 'Μέγεθος', foreColor: 'Χρώμα κειμένου',
        bold: 'Έντονα', italic: 'Πλάγια', underline: 'Υπογράμμιση',
        alignLeft: 'Στοίχιση αριστερά', alignCenter: 'Κέντρο', alignRight: 'Στοίχιση δεξιά', alignJustify: 'Πλήρης στοίχιση',
        ul: 'Λίστα με κουκκίδες', ol: 'Αριθμημένη λίστα',
        link: 'Εισαγωγή συνδέσμου', editLink: 'Επεξεργασία συνδέσμου', removeLink: 'Αφαίρεση συνδέσμου',
        image: 'Εισαγωγή εικόνας', video: 'Εισαγωγή βίντεο', emoji: 'Emoji',
        code: 'Εισαγωγή κώδικα', hr: 'Οριζόντια γραμμή',
        undo: 'Αναίρεση', redo: 'Ακύρωση αναίρεσης', html: 'Πηγαίος κώδικας (HTML)',
        expand: 'Ανάπτυξη γραμμής εργαλείων', chars: 'Χαρακτήρες', limitReached: 'Φτάσατε το όριο χαρακτήρων!'
    },
    // 20. Bengali
    bn: {
        fontName: 'ফন্ট', fontSize: 'ফন্টের আকার', foreColor: 'পাঠ্যের রঙ',
        bold: 'গাঢ়', italic: 'ইটালিক', underline: 'আন্ডারলাইন',
        alignLeft: 'বামে সারিবদ্ধ করুন', alignCenter: 'কেন্দ্রে', alignRight: 'ডানে সারিবদ্ধ করুন', alignJustify: 'জাস্টিফাই',
        ul: 'বুলেটেড তালিকা', ol: 'নম্বরযুক্ত তালিকা',
        link: 'লিঙ্ক সন্নিবেশ করুন', editLink: 'লিঙ্ক সম্পাদনা করুন', removeLink: 'লিঙ্ক সরান',
        image: 'ছবি সন্নিবেশ করুন', video: 'ভিডিও সন্নিবেশ করুন', emoji: 'ইমোজি',
        code: 'কোড ব্লক সন্নিবেশ করুন', hr: 'অনুভূমিক রেখা',
        undo: 'পূর্বাবস্থায় ফেরান', redo: 'পুনরায় করুন', html: 'সোর্স কোড (HTML)',
        expand: 'টুলবার প্রসারিত করুন', chars: 'অক্ষর', limitReached: 'অক্ষরের সীমা পৌঁছেছে!'
    }
};
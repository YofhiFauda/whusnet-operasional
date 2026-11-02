{{-- Google Fonts dimuat NON-blocking. Dulu `@import url(...)` di app.css: browser
     harus mengunduh app.css, baru tahu ada CSS font, lalu mengunduh CSS itu, baru
     file font — rantai tiga permintaan yang memblokir render (peringatan
     Lighthouse "render-blocking" + "avoid chaining critical requests", parah di
     sinyal lemah). Sekarang: preconnect lebih dulu, stylesheet dimuat async
     (media=print → all), dan teks tampil dengan font cadangan selagi menunggu
     (display=swap). <noscript> menjaga font tetap terpasang tanpa JavaScript. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"></noscript>

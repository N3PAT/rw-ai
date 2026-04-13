

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>RW-AI Chatbot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #f3f4f6 100%);
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* 🌟 Enhanced Animations */
        @keyframes messagePop {
            0% { opacity: 0; transform: scale(0.8) translateY(20px); }
            70% { transform: scale(1.05) translateY(-2px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        .msg-animate {
            animation: messagePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .feedback-btn {
            transition: all 0.2s ease;
            opacity: 0;
            animation: fadeIn 0.5s ease 0.5s forwards;
        }
.link-card {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #ffffff;
    padding: 8px 12px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin: 8px 0;
    width: 100%;
    max-width: 300px; /* คุมความกว้างไม่ให้ล้น */
    text-decoration: none !important; /* ปิดขีดเส้นใต้ */
}
.link-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}


        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .msg-text {
            white-space: pre-wrap;
            word-break: normal;          
            overflow-wrap: anywhere;    
            display: inline-block;      
            text-align: left;            
        }
      
        .no-select {
            user-select: none;
            -webkit-user-select: none;
        }

        #image-modal { transition: opacity 0.3s ease; }
        #modal-img { transition: transform 0.3s ease; }
        .ai-content img { cursor: zoom-in; transition: opacity 0.2s; }
        .ai-content img:hover { opacity: 0.9; }

        .loader {
            width: 16px; height: 16px;
            border: 2px solid #3b82f6;
            border-bottom-color: transparent;
            border-radius: 50%;
            display: inline-block;
            animation: rotation 1s linear infinite;
        }
        @keyframes rotation {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .ai-content { word-break: break-word; }
        .ai-content p { margin-bottom: 0.5rem; }
        .ai-content strong { font-weight: 600; color: #1e40af; }
        .ai-content table { border-collapse: collapse; width: 100%; margin-bottom: 0.5rem; font-size: 0.9em; }
        .ai-content th, .ai-content td { border: 1px solid #e2e8f0; padding: 0.4rem 0.6rem; }
    </style>
</head>
<body class="h-[100dvh] flex items-center justify-center p-0 sm:p-4 md:p-8 relative">

<div id="credit-popup" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300">
    <div id="popup-content" class="bg-white rounded-3xl p-6 md:p-8 max-w-sm w-full shadow-2xl transform scale-95 transition-transform duration-300">
        <div class="text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner border border-blue-200 overflow-hidden">
                <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" alt="rw image" class="max-w-full h-auto rounded-lg shadow-md">
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-1">Chatbot RW-AI</h2>
            <div class="w-12 h-1 bg-blue-500 mx-auto rounded-full mb-4"></div>
            <p class="text-sm md:text-base text-gray-600 mb-4">พัฒนาโดย ศิษย์เก่า<br><strong class="text-gray-800 text-lg">นาย ณัฏฐพัชร อินแสงจันทร์</strong><br><span class="inline-block mt-1 px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm font-medium border border-blue-100">รุ่น 78</span></p>
            <label class="flex items-center justify-center gap-2 mb-5 cursor-pointer group">
                <a href="นโยบายการใช้เทคโนโลยี Generative AI ที่ยอมรับได้ (2).pdf" id="tos-link" class="block">
    <div id="tos-box"
        class="text-left bg-gray-50 p-3 rounded-lg border border-gray-200 mb-4 text-xs text-gray-600 h-24 overflow-y-auto shadow-inner cursor-pointer hover:bg-gray-100 transition">

        <p class="font-bold text-gray-800 mb-2">
            ข้อตกลงการใช้งาน (Terms of Service)
        </p>

        <p class="text-gray-600">
            กรุณาคลิกเพื่ออ่านรายละเอียดข้อตกลงฉบับเต็ม
        </p>

    </div>
</a>

                <input type="checkbox" id="tos-checkbox" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer">
                <span class="text-sm text-gray-700">ฉันอ่านและยอมรับข้อตกลงการใช้งาน</span>
            </label>
            <button id="start-btn" disabled class="w-full bg-gray-200 text-gray-400 cursor-not-allowed font-medium py-3 rounded-xl transition-all duration-300">เริ่มต้นใช้งาน</button>
        </div>
    </div>
</div>

<div class="bg-[#f8fafc] w-full h-full sm:h-[90vh] sm:max-w-xl md:max-w-2xl lg:max-w-3xl sm:rounded-3xl shadow-2xl flex flex-col overflow-hidden relative border border-gray-200/50 transition-all duration-300">
    
    <header class="bg-gradient-to-r from-blue-700 to-blue-500 p-4 text-white flex items-center gap-3 relative z-10 shadow-sm no-select">
        <div class="relative">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-white rounded-full flex items-center justify-center font-bold text-blue-700 shadow-inner border-2 border-blue-200 overflow-hidden">
                <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" alt="RW-AI Logo" class="w-full h-full object-cover">
            </div>
            <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-blue-600 rounded-full"></div>
        </div>
        <div class="flex-1">
            <h1 class="font-bold text-base md:text-lg leading-tight tracking-wide">RW-AI Chatbot</h1>
            <p id="ai-status" class="text-[10px] md:text-xs text-blue-100 font-light flex items-center gap-1">
                <span class="w-1.5 h-1.5 bg-yellow-300 rounded-full animate-pulse"></span>
                กำลังโหลดโมเดล...
            </p>
        </div>
        <button onclick="openPopup()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        </button>
    </header>

    <main id="chat-container" class="flex-1 overflow-y-auto p-4 md:p-6 space-y-5 scroll-smooth">
        <div class="flex justify-start msg-animate">
            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full mr-2 flex-shrink-0 self-end mb-1 border border-blue-200 overflow-hidden">
                <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" class="w-full h-full object-cover">
            </div>
            <div class="bg-white text-gray-800 p-3.5 px-4 rounded-2xl rounded-bl-none shadow-sm border border-gray-100 max-w-[85%] text-sm md:text-base relative ai-content">
                <span class="absolute top-0 left-0 w-1 h-full bg-yellow-400 rounded-l-2xl rounded-bl-none"></span>
                <p>สวัสดีครับน้องๆ พี่ <strong>RW-AI</strong> ยินดีให้บริการ มีอะไรอยากสอบถามเกี่ยวกับโรงเรียนไหมครับ?</p>
            </div>
        </div>
    </main>

    <div id="step-indicator" class="hidden px-4 md:px-6 pb-2">
        <div class="flex justify-start items-center gap-3 bg-blue-50/50 p-3 rounded-2xl w-fit border border-blue-100/50">
            <span class="loader"></span>
            <span id="step-text" class="text-xs md:text-sm text-blue-700 font-medium">กำลังวิเคราะห์คำถาม...</span>
        </div>
    </div>

    <footer class="p-3 sm:p-4 md:p-5 bg-white border-t border-gray-100 z-10 shadow-[0_-4px_15px_-3px_rgba(0,0,0,0.05)]">
        <div id="suggestions" class="flex overflow-x-auto pb-3 gap-2 no-select scrollbar-hide">
            <button onclick="useSuggestion('ระเบียบการแต่งกายนักเรียนเป็นอย่างไร?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs hover:bg-blue-100">ระเบียบการแต่งกาย</button>
            <button onclick="useSuggestion('ขอแผนผังโรงเรียนหน่อยได้ไหม?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs hover:bg-blue-100">แผนผังโรงเรียน</button>
            <button onclick="useSuggestion('ขอเนื้อเพลงมาร์ช​โรงเรียนหน่อย?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs hover:bg-blue-100">เพลงมาร์ชโรงเรียน</button>
            <button onclick="useSuggestion('สิ่งศักดิ์สิทธิ์​ประจำ​โรงเรียนคืออะไร?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs hover:bg-blue-100">สิ่งศักดิ์สิทธิ์​ประจำ​โรงเรียน</button>
        </div>

        <div class="flex items-end gap-2 bg-gray-50 border border-gray-200 rounded-2xl p-2 focus-within:ring-2 focus-within:ring-blue-400 focus-within:bg-white transition-all">
            <textarea id="user-input" placeholder="พิมพ์คำถามที่นี่..." rows="1" class="flex-1 resize-none bg-transparent px-3 py-2 focus:outline-none text-sm md:text-base text-gray-700 max-h-32"></textarea>
            <button type="button" onclick="sendMessage()" id="send-btn" class="bg-blue-600 text-white w-10 h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center hover:bg-blue-700 hover:rotate-12 active:scale-90 transition-all shadow-md shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </div>
    </footer>
</div>
<div id="link-modal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300">
    <div id="link-modal-content" class="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl transform scale-95 transition-all">
        <div class="text-center">
            <h3 class="text-lg font-bold mb-2">ยืนยันการเปิดลิงก์</h3>
            <p class="text-sm text-gray-500 mb-4">คุณต้องการเปิดไปยังเว็บไซต์ภายนอกหรือไม่?</p>
            <p id="target-link-display" class="text-[10px] text-blue-600 break-all bg-gray-50 p-2 rounded mb-4"></p>
            <div class="flex gap-2">
                <button onclick="closeLinkModal()" class="flex-1 py-2 bg-gray-100 rounded-xl text-sm">ยกเลิก</button>
                <a id="confirm-link-btn" href="#" target="_blank" onclick="closeLinkModal()" class="flex-1 py-2 bg-blue-600 text-white rounded-xl text-sm text-center">ยืนยัน</a>
            </div>
        </div>
    </div>
</div>

<div id="image-modal" class="fixed inset-0 z-[70] bg-black/90 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all" onclick="closeImageModal()">
    <img id="modal-img" src="" class="max-w-full max-h-full rounded-lg transform scale-95 transition-all">
</div>

<script>
    marked.setOptions({ breaks: true, gfm: true });
    const inputField = document.getElementById('user-input');
    const container = document.getElementById('chat-container');
    const stepIndicator = document.getElementById('step-indicator');
    const sendBtn = document.getElementById('send-btn');
    const aiStatus = document.getElementById('ai-status');

    function openPopup() {
        document.getElementById('credit-popup').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('popup-content').classList.replace('scale-95', 'scale-100');
    }
    function closePopup() {
        document.getElementById('credit-popup').classList.add('opacity-0', 'pointer-events-none');
        document.getElementById('popup-content').classList.replace('scale-100', 'scale-95');
        setTimeout(() => inputField.focus(), 300);
    }
    function scrollToBottom() { container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' }); }

    function formatLinks(text) {
    // Regex หา URL ทั่วไปที่ไม่ใช่รูปภาพ
    const urlRegex = /(https?:\/\/[^\s<"']+(?<!\.(?:png|jpg|jpeg|gif|webp)))/gi;
    
    return text.replace(urlRegex, (url) => {
        // ถ้าเป็นส่วนหนึ่งของ tag HTML อยู่แล้ว (เช่น src="..." หรือ href="...") ไม่ต้องยุ่ง
        if (text.includes(`src="${url}"`) || text.includes(`href="${url}"`)) {
            return url;
        }
        
        // แปลงเป็น Link Card
        return `
        <div class="my-2">
            <a href="${url}" class="link-card hover:bg-blue-50 transition-all group">
                <div class="bg-blue-600 p-2 rounded-lg text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                </div>
                <div class="flex flex-col overflow-hidden">
                    <span class="text-[10px] text-gray-400 uppercase font-bold">Link</span>
                    <span class="text-blue-600 font-medium truncate text-xs">${url}</span>
                </div>
            </a>
        </div>`;
    });
}


function autoResizeTextarea() {
    const inputField = document.getElementById('user-input');
    if (inputField) {
        inputField.style.height = 'auto'; // รีเซ็ตความสูงก่อนคำนวณใหม่
        // ปรับความสูงตามเนื้อหาจริง แต่ไม่เกิน 128px
        inputField.style.height = Math.min(inputField.scrollHeight, 128) + 'px';
    }
}
        async function sendMessage() {
    const message = inputField.value.trim();
    if (!message || sendBtn.disabled) return; // กันการกดรัว

    sendBtn.disabled = true; // ล็อคปุ่มส่ง
    inputField.disabled = true; // ล็อคช่องพิมพ์
    
    inputField.value = '';
    autoResizeTextarea(); // รีเซ็ตความสูงช่องพิมพ์กลับเป็น 1 บรรทัด
    
    appendMessage(message, true);
    stepIndicator.classList.remove('hidden');
    scrollToBottom();

    try {
        const response = await fetch('chat_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message })
        });
        const data = await response.json();
        stepIndicator.classList.add('hidden');
        appendMessage(data.response || 'ระบบขัดข้อง', false, data.log_id);
    } catch (e) {
        stepIndicator.classList.add('hidden');
        appendMessage('เชื่อมต่อล้มเหลว', false);
    } finally {
        sendBtn.disabled = false; // ปลดล็อคปุ่ม
        inputField.disabled = false; // ปลดล็อคช่องพิมพ์
        inputField.focus();
    }
}


    function appendMessage(message, isUser = true, logId = null) {
    const time = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    
    // --- แก้ไขตรงก้อนนี้ ---
    let htmlContent;
    if (isUser) {
        htmlContent = message;
    } else {
        // 1. จัดการลิงก์ให้เป็น Card ก่อน (แต่ข้ามไฟล์ภาพ)
        let processedText = formatLinks(message);
        // 2. แปลง Markdown (รวมถึงรูปภาพที่ AI ส่งมาแบบ Markdown)
        htmlContent = marked.parse(processedText);
    }
    // -----------------------

    const feedback = (!isUser && logId) ? `<div class="flex gap-2 mt-2"><button onclick="sendFeedback(${logId}, 1, this)" class="text-[10px] px-2 py-1 bg-gray-100 rounded-md">ประโยคมีประโยชน์</button><button onclick="sendFeedback(${logId}, 0, this)" class="text-[10px] px-2 py-1 bg-gray-100 rounded-md">ประโยคไม่ชัดเจน</button></div>` : '';

    const msgHtml = `<div class="flex ${isUser ? 'justify-end' : 'justify-start'} msg-animate w-full">
        ${!isUser ? '<div class="w-8 h-8 rounded-full mr-2 self-end mb-1 shrink-0 overflow-hidden border border-blue-200"><img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" class="w-full h-full object-cover"></div>' : ''}
        <div class="flex flex-col ${isUser ? 'items-end' : 'items-start'} max-w-[85%]">
            <div class="${isUser ? 'bg-blue-600 text-white rounded-br-none shadow-md' : 'bg-white text-gray-800 rounded-bl-none shadow-sm border border-gray-100'} p-3.5 px-4 rounded-2xl text-sm ai-content">
                ${htmlContent}
            </div>
            ${feedback}
            <span class="text-[10px] text-gray-400 mt-1">${time}</span>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', msgHtml);
    scrollToBottom();
}


    async function sendFeedback(logId, rating, btn) {
        btn.parentElement.innerHTML = '<span class="text-[10px] text-blue-500">ขอบคุณครับ!</span>';
        await fetch('update_feedback.php', { method: 'POST', body: JSON.stringify({ log_id: logId, rating }) });
    }

    // Modal Controls
    function openLinkModal(url) {
        document.getElementById('target-link-display').textContent = url;
        document.getElementById('confirm-link-btn').href = url;
        document.getElementById('link-modal').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('link-modal-content').classList.replace('scale-95', 'scale-100');
    }
    function closeLinkModal() {
        document.getElementById('link-modal').classList.add('opacity-0', 'pointer-events-none');
        document.getElementById('link-modal-content').classList.replace('scale-100', 'scale-95');
    }
    function openImageModal(src) {
        document.getElementById('modal-img').src = src;
        document.getElementById('image-modal').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('modal-img').classList.replace('scale-95', 'scale-100');
    }
    function closeImageModal() {
        document.getElementById('image-modal').classList.add('opacity-0', 'pointer-events-none');
        document.getElementById('modal-img').classList.replace('scale-100', 'scale-95');
    }

    // Container Event Delegation (Handle images and links)
    container.addEventListener('click', (e) => {
        const img = e.target.closest('.ai-content img');
        if (img) return openImageModal(img.src);
        
        const link = e.target.closest('.ai-content a');
        if (link) {
            const url = link.getAttribute('href');
            if (url.match(/\.(jpeg|jpg|gif|png)$/) != null) return; 
            e.preventDefault();
            openLinkModal(url);
        }
    });

    function useSuggestion(text) { 
    inputField.value = text; 
    autoResizeTextarea(); // เพิ่มบรรทัดนี้
    sendMessage(); 
}

    inputField.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });

    // TOS Logic
    document.getElementById('tos-checkbox').addEventListener('change', function() {
        const btn = document.getElementById('start-btn');
        btn.disabled = !this.checked;
        btn.className = this.checked ? "w-full bg-blue-600 text-white font-medium py-3 rounded-xl transition-all" : "w-full bg-gray-200 text-gray-400 font-medium py-3 rounded-xl";
    });
    document.getElementById('start-btn').addEventListener('click', closePopup);

    window.onload = () => {
        aiStatus.innerHTML = '<span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span> AI พร้อมใช้งานแล้ว';
        setTimeout(openPopup, 500);
    };

    inputField.addEventListener('input', autoResizeTextarea);


</script>
</body>
</html>
          

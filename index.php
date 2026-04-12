
<?php
// chat.php
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>RW-AI Chatbot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- เพิ่ม Library marked.js สำหรับแปลง Markdown -->
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
        
        @keyframes messageSlideIn {
            from { opacity: 0; transform: translateY(15px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .msg-animate { animation: messageSlideIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        
        .no-select {
            user-select: none;
            -webkit-user-select: none;
        }
        .msg-text {
            white-space: pre-wrap;
            word-break: break-word;
        }
        
/* Image Preview Modal */
#image-modal {
    transition: opacity 0.3s ease;
}
#modal-img {
    transition: transform 0.3s ease;
}
/* สไตล์เพื่อให้รูปภาพในแชทดูรู้ว่าคลิกได้ */
.ai-content img {
    cursor: zoom-in;
    transition: opacity 0.2s;
}
.ai-content img:hover {
    opacity: 0.9;
}

        /* Loading Spinner */
        .loader {
            width: 16px;
            height: 16px;
            border: 2px solid #3b82f6;
            border-bottom-color: transparent;
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }
        @keyframes rotation {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ตกแต่งเนื้อหาที่มาจาก AI (Markdown) ให้สวยงาม */
        .ai-content { word-break: break-word; }
        .ai-content p { margin-bottom: 0.5rem; }
        .ai-content p:last-child { margin-bottom: 0; }
        .ai-content strong { font-weight: 600; color: #1e40af; }
        .ai-content em { font-style: italic; color: #475569; }
        .ai-content ul { list-style-type: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .ai-content ol { list-style-type: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .ai-content li { margin-bottom: 0.25rem; }
        .ai-content li p { display: inline; }
        .ai-content h1, .ai-content h2, .ai-content h3 { font-weight: 700; color: #1e3a8a; margin-top: 0.75rem; margin-bottom: 0.5rem; }
        .ai-content h1 { font-size: 1.25em; }
        .ai-content h2 { font-size: 1.15em; }
        .ai-content h3 { font-size: 1.05em; }
        .ai-content a { color: #2563eb; text-decoration: underline; text-underline-offset: 2px; }
        .ai-content a:hover { color: #1d4ed8; }
        .ai-content code { background-color: #f1f5f9; color: #ef4444; padding: 0.15rem 0.35rem; border-radius: 0.25rem; font-family: monospace; font-size: 0.9em; }
        .ai-content pre { background-color: #1e293b; color: #f8fafc; padding: 0.75rem; border-radius: 0.5rem; overflow-x: auto; margin-bottom: 0.5rem; }
        .ai-content pre code { background-color: transparent; color: inherit; padding: 0; font-size: 0.85em; }
        .ai-content blockquote { border-left: 3px solid #cbd5e1; padding-left: 0.75rem; color: #64748b; font-style: italic; margin-bottom: 0.5rem; }
        .ai-content table { border-collapse: collapse; width: 100%; margin-bottom: 0.5rem; font-size: 0.9em; }
        .ai-content th, .ai-content td { border: 1px solid #e2e8f0; padding: 0.4rem 0.6rem; text-align: left; }
        .ai-content th { background-color: #f8fafc; font-weight: 600; color: #334155; }
    </style>
</head>
<body class="h-[100dvh] flex items-center justify-center p-0 sm:p-4 md:p-8 relative">

    <!-- ========== POPUP ผู้พัฒนา ========== -->
<div id="credit-popup" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300">
    <div id="popup-content" class="bg-white rounded-3xl p-6 md:p-8 max-w-sm w-full shadow-2xl transform scale-95 transition-transform duration-300">
        <div class="text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner border border-blue-200 overflow-hidden">
                <img
                    src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png"
                    alt="rw image"
                    class="max-w-full h-auto rounded-lg shadow-md"
                >
            </div>

            <h2 class="text-xl font-bold text-gray-800 mb-1">Chatbot RW-AI</h2>
            <div class="w-12 h-1 bg-blue-500 mx-auto rounded-full mb-4"></div>

            <p class="text-sm md:text-base text-gray-600 mb-4 leading-relaxed">
                พัฒนาโดย ศิษย์เก่า<br>
                <strong class="text-gray-800 text-lg">นาย ณัฏฐพัชร อินแสงจันทร์</strong><br>
                <span class="inline-block mt-1 px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm font-medium border border-blue-100">รุ่น 78</span>
            </p>

            <a href="https://instagram.com/n19axor_" target="_blank" class="inline-flex items-center justify-center gap-2 mb-4 px-4 py-2 bg-gradient-to-r from-pink-50 to-purple-50 hover:from-pink-100 hover:to-purple-100 text-pink-600 rounded-xl transition-all border border-pink-100 shadow-sm group">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="group-hover:scale-110 transition-transform">
                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                </svg>
                <span class="text-sm font-medium">IG: @n19axor_</span>
            </a>

<a href="privacy_policy.php" id="tos-link" class="block">
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

            <label class="flex items-center justify-center gap-2 mb-5 cursor-pointer group">
                <input
                    type="checkbox"
                    id="tos-checkbox"
                    class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer"
                >
                <span class="text-sm text-gray-700 group-hover:text-blue-600 transition-colors select-none">
                    ฉันอ่านและยอมรับข้อตกลงการใช้งาน
                </span>
            </label>

            <button
                id="start-btn"
                disabled
                class="w-full bg-gray-200 text-gray-400 cursor-not-allowed font-medium py-3 rounded-xl transition-all duration-300"
            >
                เริ่มต้นใช้งาน
            </button>
        </div>
    </div>
</div>

<script>
    const box = document.getElementById('tos-box');
    const linkBox = document.getElementById('tos-link');
    const checkbox = document.getElementById('tos-checkbox');
    const btn = document.getElementById('start-btn');

    let unlocked = false;

    box.addEventListener('scroll', () => {
        const atBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 5;

        if (atBottom) {
            unlocked = true;
            linkBox.classList.remove('pointer-events-none', 'opacity-50', 'cursor-not-allowed');
            linkBox.classList.add('opacity-100', 'cursor-pointer');
        }
    });

    linkBox.addEventListener('click', () => {
        if (!unlocked) return;
        window.location.href = 'privacy_policy.php';
    });

    checkbox.addEventListener('change', () => {
        if (checkbox.checked) {
            btn.disabled = false;
            btn.className = "w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-xl transition-all duration-300";
        } else {
            btn.disabled = true;
            btn.className = "w-full bg-gray-200 text-gray-400 cursor-not-allowed font-medium py-3 rounded-xl transition-all duration-300";
        }
    });

    btn.addEventListener('click', () => {
        if (!checkbox.checked) return;
        closePopup();
    });
</script>

    <!-- ================================== -->

    <div class="bg-[#f8fafc] w-full h-full sm:h-[90vh] sm:max-w-xl md:max-w-2xl lg:max-w-3xl sm:rounded-3xl shadow-2xl flex flex-col overflow-hidden relative border border-gray-200/50 transition-all duration-300">
        
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-700 to-blue-500 p-4 text-white flex items-center gap-3 relative z-10 shadow-sm no-select">
            <div class="relative">
<div class="w-10 h-10 md:w-12 md:h-12 bg-white rounded-full flex items-center justify-center font-bold text-blue-700 text-base md:text-lg shadow-inner border-2 border-blue-200 overflow-hidden">
    <img 
        src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" 
        alt="RW-AI Logo" 
        class="w-full h-full object-cover"
    >
</div>

                <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-blue-600 rounded-full"></div>
            </div>
            <div class="flex-1">
                <h1 class="font-bold text-base md:text-lg leading-tight tracking-wide">RW-AI Chatbot</h1>
                <p id="ai-status" class="text-[10px] md:text-xs text-blue-100 font-light flex items-center gap-1">
                    <span class="w-1.5 h-1.5 bg-yellow-300 rounded-full animate-pulse"></span>
                    กำลังโหลดโมเดลในเครื่อง...
                </p>
            </div>
            <!-- ปุ่มไอคอนเปิดดูเครดิตซ้ำ (เผื่ออยากดูอีกรอบ) -->
            <button onclick="openPopup()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors" type="button" title="ข้อมูลผู้พัฒนา">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </button>
        </header>

        <!-- Chat Container -->
        <main id="chat-container" class="flex-1 overflow-y-auto p-4 md:p-6 space-y-5 scroll-smooth">
            <!-- ข้อความต้อนรับ -->
            <div class="flex justify-start msg-animate origin-bottom-left">
                <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center mr-2 flex-shrink-0 self-end mb-1 border border-blue-200">
<span class="inline-flex items-center justify-center w-[1.2em] h-[1.2em] align-text-bottom">
    <img 
        src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" 
        alt="RW-AI Icon" 
        class="w-full h-full object-cover rounded-full"
    >
</span>
                </div>
                <div class="bg-white text-gray-800 p-3.5 px-4 md:p-4 md:px-5 rounded-2xl rounded-bl-none shadow-sm border border-gray-100 max-w-[85%] md:max-w-[75%] text-sm md:text-base leading-relaxed relative group ai-content">
                    <span class="absolute top-0 left-0 w-1 h-full bg-yellow-400 rounded-l-2xl rounded-bl-none"></span>
                    <p>สวัสดีครับน้องๆ พี่ <strong>RW-AI</strong> ยินดีให้บริการ มีอะไรอยากสอบถามเกี่ยวกับโรงเรียนไหมครับ?</p>
                </div>
            </div>
        </main>

        <!-- Step Indicator -->
        <div id="step-indicator" class="hidden px-4 md:px-6 pb-2">
            <div class="flex justify-start msg-animate origin-bottom-left">
                <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center mr-2 flex-shrink-0 self-end mb-1 border border-blue-100">
<span class="inline-flex items-center justify-center w-[1.2em] h-[1.2em] align-text-bottom">
    <img 
        src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" 
        alt="RW-AI Icon" 
        class="w-full h-full object-cover rounded-full"
    >
</span>
                </div>
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-3 px-4 rounded-2xl rounded-bl-none shadow-sm border border-blue-100 flex gap-3 items-center h-12">
                    <span class="loader"></span>
                    <span id="step-text" class="text-xs md:text-sm text-blue-700 font-medium tracking-wide">กำลังวิเคราะห์คำถาม...</span>
                </div>
            </div>
        </div>

        <!-- Footer / Input -->
        <footer class="p-3 sm:p-4 md:p-5 bg-white border-t border-gray-100 z-10 shadow-[0_-4px_15px_-3px_rgba(0,0,0,0.05)]">
            
            <div id="suggestions" class="flex overflow-x-auto pb-3 gap-2 no-select scrollbar-hide" style="-ms-overflow-style: none; scrollbar-width: none;">
                <button onclick="useSuggestion('ระเบียบการแต่งกายนักเรียนเป็นอย่างไร?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs md:text-sm hover:bg-blue-100 transition-colors shrink-0">ระเบียบการแต่งกาย</button>
                <button onclick="useSuggestion('ขอเนื้อเพลงมาร์ชโรงเรียนฤทธิยะวรรณาลัย')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs md:text-sm hover:bg-blue-100 transition-colors shrink-0">เพลงมาร์ชโรงเรียน</button>
                <button onclick="useSuggestion('ตารางเวลาเรียนและเวลาพักกลางวัน')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs md:text-sm hover:bg-blue-100 transition-colors shrink-0">เวลาเรียน/เวลาพัก</button>
                <button onclick="useSuggestion('ขอแผนผังโรงเรียนหน่อยได้ไหม?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs md:text-sm hover:bg-blue-100 transition-colors shrink-0">แผนผังโรงเรียน</button>
                <button onclick="useSuggestion('สิ่งศักดิ์สิทธิ์ประจำโรงเรียน?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs md:text-sm hover:bg-blue-100 transition-colors shrink-0">สิ่งศักดิ์สิทธิ์ประจำโรงเรียน</button>
            </div>

            <div class="text-center mb-2">
                <p class="text-[10px] md:text-xs text-gray-400 font-light flex items-center justify-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    โปรดใช้วิจารณญาณในการใช้ ข้อมูลอาจมีการคลาดเคลื่อน
                </p>
            </div>

            <div class="flex items-end gap-2 bg-gray-50 border border-gray-200 rounded-2xl p-2 pr-2 focus-within:ring-2 focus-within:ring-blue-400 focus-within:bg-white focus-within:border-transparent transition-all shadow-inner">
                <textarea id="user-input"
                    placeholder="พิมพ์คำถามที่นี่..."
                    rows="1"
                    class="flex-1 resize-none bg-transparent px-3 py-2 md:py-3 focus:outline-none text-sm md:text-base text-gray-700 placeholder-gray-400 max-h-32 overflow-y-auto"
                    autocomplete="off"></textarea>
                <button type="button" onclick="sendMessage()" id="send-btn"
                    class="bg-blue-600 text-white w-10 h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center hover:bg-blue-700 hover:scale-105 active:scale-95 transition-all shadow-md group disabled:opacity-50 disabled:cursor-not-allowed shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="md:w-5 md:h-5 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </footer>

    </div>

<script>
// ตั้งค่า marked.js ให้รองรับการขึ้นบรรทัดใหม่ปกติ
marked.setOptions({
    breaks: true,
    gfm: true
});

const inputField = document.getElementById('user-input');
const container = document.getElementById('chat-container');
const stepIndicator = document.getElementById('step-indicator');
const stepText = document.getElementById('step-text');
const sendBtn = document.getElementById('send-btn');
const aiStatus = document.getElementById('ai-status');

// Popup Functions
function openPopup() {
    const popup = document.getElementById('credit-popup');
    const popupContent = document.getElementById('popup-content');
    popup.classList.remove('opacity-0', 'pointer-events-none');
    popupContent.classList.remove('scale-95');
    popupContent.classList.add('scale-100');
}

function closePopup() {
    const popup = document.getElementById('credit-popup');
    const popupContent = document.getElementById('popup-content');
    popup.classList.add('opacity-0', 'pointer-events-none');
    popupContent.classList.remove('scale-100');
    popupContent.classList.add('scale-95');
    
    setTimeout(() => {
        inputField.focus();
    }, 300);
}

// สถานะออนไลน์
aiStatus.innerHTML = '<span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse shadow-[0_0_5px_#4ade80]"></span> AI ออนไลน์พร้อมใช้งานแล้ว';

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function nl2brSafe(text) {
    return escapeHtml(text).replace(/\n/g, '<br>');
}

function scrollToBottom() {
    requestAnimationFrame(() => {
        container.scrollTo({
            top: container.scrollHeight,
            behavior: 'smooth'
        });
    });
}

function appendMessage(message, isUser = true) {
    const time = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    let msgHtml = '';

    if (isUser) {
        const safeMessage = nl2brSafe(message);
        msgHtml = `
        <div class="flex justify-end msg-animate origin-bottom-right">
            <div class="flex flex-col items-end">
                <div class="bg-blue-600 text-white p-3.5 px-4 md:p-4 md:px-5 rounded-2xl rounded-br-none shadow-md max-w-[85%] md:max-w-[75%] text-sm md:text-base leading-relaxed msg-text">
                    ${safeMessage}
                </div>
                <span class="text-[10px] md:text-xs text-gray-400 mt-1 mr-1">${time}</span>
            </div>
        </div>`;
    } else {
        const markdownMessage = marked.parse(message);
        msgHtml = `
        <div class="flex justify-start msg-animate origin-bottom-left">
            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center mr-2 flex-shrink-0 self-end mb-5 border border-blue-200">
<span class="inline-flex items-center justify-center w-[1.2em] h-[1.2em] align-text-bottom">
    <img 
        src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" 
        alt="RW-AI Icon" 
        class="w-full h-full object-cover rounded-full"
    >
</span>
            </div>
            <div class="flex flex-col items-start w-full max-w-[85%] md:max-w-[75%]">
                <div class="bg-white text-gray-800 p-3.5 px-4 md:p-4 md:px-5 rounded-2xl rounded-bl-none shadow-sm border border-gray-100 text-sm md:text-base leading-relaxed relative ai-content w-full">
                    ${markdownMessage}
                </div>
                <span class="text-[10px] md:text-xs text-gray-400 mt-1 ml-1">${time}</span>
            </div>
        </div>`;
    }

    container.insertAdjacentHTML('beforeend', msgHtml);
    scrollToBottom();
}

function autoResizeTextarea() {
    inputField.style.height = 'auto';
    inputField.style.height = Math.min(inputField.scrollHeight, 128) + 'px';
}

const processingSteps = [
    { text: "กำลังค้นหาข้อมูลจากฐานข้อมูล...", minTime: 600 },
    { text: "กำลังประมวลผลคำตอบ...", minTime: 800 },
    { text: "ตรวจสอบความถูกต้องของข้อมูล...", minTime: 600 }
];

async function animateSteps() {
    for (let step of processingSteps) {
        stepText.innerText = step.text;
        await new Promise(resolve => setTimeout(resolve, step.minTime));
    }
}

async function sendMessage() {
    // ใช้ .trim() เพื่อตัดช่องว่างด้านหน้าและหลังทิ้ง ป้องกันปัญหาพื้นที่ว่างยาวๆ
    const message = inputField.value.trim(); 
    
    if (!message) return; // ถ้ามีแต่ space จะไม่ส่ง

    inputField.value = ''; // ล้างช่องพิมพ์
    autoResizeTextarea();

    inputField.disabled = true;
    sendBtn.disabled = true;

    appendMessage(message, true);
    
    stepIndicator.classList.remove('hidden');
    scrollToBottom();

    try {
        const [apiResponse] = await Promise.all([
            fetch('chat_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message })
            }).catch(e => { throw new Error("Network Error") }),
            animateSteps() 
        ]);

        const text = await apiResponse.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch {
            throw new Error('ไม่ใช่ JSON: ' + text);
        }

        stepIndicator.classList.add('hidden');
        appendMessage(data.response || 'AI ไม่ตอบกลับ', false);

    } catch (error) {
        console.error(error);
        stepText.innerText = "เกิดข้อผิดพลาด กรุณาลองใหม่";
        stepText.classList.replace("text-blue-700", "text-red-500");
        
        setTimeout(() => {
            stepIndicator.classList.add('hidden');
            stepText.classList.replace("text-red-500", "text-blue-700");
            appendMessage('ระบบเชื่อมต่อฐานข้อมูลล้มเหลว หรือตอบสนองช้าเกินไป', false);
        }, 1500);
    } finally {
        inputField.disabled = false;
        sendBtn.disabled = false;
        inputField.focus();
        scrollToBottom();
    }
}

inputField.addEventListener('input', autoResizeTextarea);

inputField.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

window.onload = () => {
    // ให้แสดง Popup ทันทีที่โหลดหน้าเสร็จ
    setTimeout(openPopup, 100); 
    autoResizeTextarea();
};
</script>
<script>
    function toggleStartButton() {
        const checkbox = document.getElementById('tos-checkbox');
        const btn = document.getElementById('start-btn');
        
        if (checkbox.checked) {
            // เมื่อติ๊กยอมรับ ให้เปิดใช้งานปุ่มและเปลี่ยนสีเป็นสีน้ำเงิน
            btn.disabled = false;
            btn.className = "w-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-700 hover:to-blue-600 text-white font-medium py-3 rounded-xl transition-all shadow-md hover:shadow-lg active:scale-[0.98] duration-300 cursor-pointer";
        } else {
            // เมื่อเอาติ๊กออก ให้ล็อกปุ่มและเปลี่ยนเป็นสีเทา
            btn.disabled = true;
            btn.className = "w-full bg-gray-200 text-gray-400 cursor-not-allowed font-medium py-3 rounded-xl transition-all duration-300";
        }
    }
</script>
<script>// ฟังก์ชันเปิด Modal ขยายรูป
function openImageModal(src) {
    const modal = document.getElementById('image-modal');
    const modalImg = document.getElementById('modal-img');
    
    modalImg.src = src;
    modal.classList.remove('opacity-0', 'pointer-events-none');
    
    setTimeout(() => {
        modalImg.classList.remove('scale-95');
        modalImg.classList.add('scale-100');
    }, 10);
}

// ฟังก์ชันปิด Modal
function closeImageModal() {
    const modal = document.getElementById('image-modal');
    const modalImg = document.getElementById('modal-img');
    
    modalImg.classList.remove('scale-100');
    modalImg.classList.add('scale-95');
    modal.classList.add('opacity-0', 'pointer-events-none');
    
    setTimeout(() => {
        modalImg.src = '';
    }, 300);
}

// เพิ่ม Event Listener ให้กับรูปภาพที่เกิดขึ้นใน Chat Container
container.addEventListener('click', function(e) {
    // ถ้าสิ่งที่คลิกคือรูปภาพ (img) ที่อยู่ในกล่องข้อความ AI
    if (e.target.tagName === 'IMG' && e.target.closest('.ai-content')) {
        openImageModal(e.target.src);
    }
});
</script>
<div id="image-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 opacity-0 pointer-events-none transition-opacity duration-300" onclick="closeImageModal()">
    <button class="absolute top-5 right-5 text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
    <img id="modal-img" src="" class="max-w-[95%] max-h-[90dvh] rounded-lg shadow-2xl object-contain scale-95 transition-transform duration-300" alt="Full Preview">
</div>
<script>
  // ฟังก์ชันสำหรับใช้คำถามแนะนำ
function useSuggestion(text) {
    if (inputField.disabled) return; // ป้องกันการกดซ้ำขณะ AI กำลังตอบ
    inputField.value = text;
    autoResizeTextarea();
    sendMessage();
}

</script>
</body>
</html>


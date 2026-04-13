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

<script>
marked.setOptions({ breaks: true, gfm: true });
const inputField = document.getElementById('user-input');
const container = document.getElementById('chat-container');
const stepIndicator = document.getElementById('step-indicator');
const stepText = document.getElementById('step-text');
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

aiStatus.innerHTML = '<span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span> AI พร้อมใช้งานแล้ว';

function scrollToBottom() {
    requestAnimationFrame(() => {
        container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
    });
}
// 🛡️ [UPDATE] ฟังก์ชันส่ง Feedback 👍/👎 (แก้ไขเพื่อให้ PHP รับค่าได้)
async function sendFeedback(logId, rating, btnElement) {
    if (!logId) return;
    
    // แสดง UI ว่ากดแล้ว
    const parent = btnElement.parentElement;
    parent.innerHTML = '<span class="text-[10px] text-blue-500 animate-pulse">ขอบคุณสำหรับ Feedback ครับ!</span>';

    try {
        await fetch('update_feedback.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json' // 🌟 เพิ่มบรรทัดนี้ครับ สำคัญมาก!
            },
            body: JSON.stringify({ 
                log_id: parseInt(logId), 
                rating: parseInt(rating) 
            })
        });
    } catch (e) { 
        console.error("Feedback Error:", e); 
    }
}


function appendMessage(message, isUser = true, logId = null) {
    const time = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    let msgHtml = '';

    if (isUser) {
        msgHtml = `
        <div class="flex justify-end msg-animate w-full">
            <div class="flex flex-col items-end max-w-[85%]">
                <div class="bg-blue-600 text-white p-3.5 px-4 rounded-2xl rounded-br-none shadow-md text-sm md:text-base msg-text">${message}</div>
                <span class="text-[10px] text-gray-400 mt-1 mr-1">${time}</span>
            </div>
        </div>`;
    } else {
        const markdownMessage = marked.parse(message);
        // เพิ่มส่วนปุ่ม Feedback ถ้าไม่ใช่ข้อความจาก User
        const feedbackHtml = logId ? `
            <div class="flex gap-2 mt-2 feedback-btn">
                <button onclick="sendFeedback(${logId}, 1, this)" class="p-1 px-2 rounded-lg border border-gray-100 hover:bg-blue-50 hover:text-blue-600 transition-colors text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                </button>
                <button onclick="sendFeedback(${logId}, -1, this)" class="p-1 px-2 rounded-lg border border-gray-100 hover:bg-red-50 hover:text-red-600 transition-colors text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"></path></svg>
                </button>
            </div>
        ` : '';

        msgHtml = `
        <div class="flex justify-start msg-animate">
            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full mr-2 flex-shrink-0 self-end mb-5 border border-blue-200 overflow-hidden">
                <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" class="w-full h-full">
            </div>
            <div class="flex flex-col items-start max-w-[85%] w-full">
                <div class="bg-white text-gray-800 p-3.5 px-4 rounded-2xl rounded-bl-none shadow-sm border border-gray-100 text-sm md:text-base leading-relaxed ai-content w-full">
                    ${markdownMessage}
                </div>
                ${feedbackHtml}
                <span class="text-[10px] text-gray-400 mt-1 ml-1">${time}</span>
            </div>
        </div>`;
    }
    container.insertAdjacentHTML('beforeend', msgHtml);
    scrollToBottom();
}

async function sendMessage() {
    const message = inputField.value.trim();
    if (!message) return;

    inputField.value = '';
    inputField.style.height = 'auto';
    inputField.disabled = true;
    sendBtn.disabled = true;

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
        
        // 🛡️ [UPDATE] นำ response และ log_id ที่ได้จาก PHP มาแสดง
        appendMessage(data.response || 'พี่ขอโทษครับ ระบบขัดข้องชั่วคราว', false, data.log_id);

    } catch (error) {
        stepIndicator.classList.add('hidden');
        appendMessage('ระบบเชื่อมต่อล้มเหลว หรือน้องถามเร็วเกินไปครับ ลองใหม่อีกครั้งนะ', false);
    } finally {
        inputField.disabled = false;
        sendBtn.disabled = false;
        inputField.focus();
    }
}

function useSuggestion(text) {
    if (inputField.disabled) return;
    inputField.value = text;
    sendMessage();
}

inputField.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 128) + 'px';
});

inputField.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

window.onload = () => setTimeout(openPopup, 100);
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

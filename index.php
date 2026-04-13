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
    
    <!-- Library marked.js สำหรับแปลง Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #eef2f6; /* พื้นหลังสีเทาอมฟ้าสบายตา */
            background-image: radial-gradient(circle at top right, #e0e7ff, transparent 40%),
                              radial-gradient(circle at bottom left, #dbeafe, transparent 40%);
            background-attachment: fixed;
        }

        /* Scrollbar อ่อนนุ่ม */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        
        /* Animation แชทเด้ง */
        @keyframes messagePop {
            0% { opacity: 0; transform: translateY(10px) scale(0.97); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        .msg-animate { animation: messagePop 0.3s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
        
        .no-select { user-select: none; -webkit-user-select: none; }
        .msg-text { white-space: pre-wrap; word-break: break-word; }
        
        /* Image Preview Modal */
        #image-modal { transition: opacity 0.3s ease; backdrop-filter: blur(5px); }
        #modal-img { transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); }
        .ai-content img { cursor: zoom-in; transition: transform 0.2s, opacity 0.2s; border-radius: 0.75rem; border: 1px solid #e2e8f0; }
        .ai-content img:hover { opacity: 0.95; transform: scale(1.02); }

        /* Modern Typing Indicator (จุดกระโดด) */
        .typing-dots span {
            display: inline-block;
            width: 6px;
            height: 6px;
            background-color: #3b82f6;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
            margin: 0 2px;
        }
        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); opacity: 0.4; }
            40% { transform: scale(1); opacity: 1; }
        }

        /* Markdown Styling (AI Content) */
        .ai-content p { margin-bottom: 0.6rem; line-height: 1.6; }
        .ai-content p:last-child { margin-bottom: 0; }
        .ai-content strong { font-weight: 600; color: #1e3a8a; }
        .ai-content em { font-style: italic; color: #475569; }
        .ai-content ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 0.75rem; color: #334155; }
        .ai-content ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 0.75rem; color: #334155; }
        .ai-content li { margin-bottom: 0.25rem; }
        .ai-content h1, .ai-content h2, .ai-content h3 { font-weight: 700; color: #1e40af; margin-top: 1rem; margin-bottom: 0.5rem; }
        .ai-content h1 { font-size: 1.3em; }
        .ai-content h2 { font-size: 1.2em; }
        .ai-content h3 { font-size: 1.1em; }
        .ai-content a { color: #2563eb; text-decoration: none; border-bottom: 1px solid transparent; transition: border-color 0.2s; font-weight: 500; }
        .ai-content a:hover { border-bottom-color: #2563eb; }
        .ai-content code { background-color: #f1f5f9; color: #e11d48; padding: 0.15rem 0.4rem; border-radius: 0.375rem; font-family: monospace; font-size: 0.85em; }
        .ai-content pre { background-color: #0f172a; color: #f8fafc; padding: 1rem; border-radius: 0.75rem; overflow-x: auto; margin-bottom: 0.75rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); }
        .ai-content pre code { background-color: transparent; color: inherit; padding: 0; font-size: 0.9em; }
        .ai-content blockquote { border-left: 4px solid #93c5fd; padding-left: 1rem; color: #475569; font-style: italic; margin-bottom: 0.75rem; background: #f8fafc; padding-top: 0.5rem; padding-bottom: 0.5rem; border-radius: 0 0.5rem 0.5rem 0; }
        .ai-content table { width: 100%; margin-bottom: 0.75rem; border-radius: 0.5rem; overflow: hidden; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; }
        .ai-content th, .ai-content td { padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .ai-content th { background-color: #f1f5f9; font-weight: 600; color: #1e293b; }
        .ai-content tr:last-child td { border-bottom: none; }
    </style>
</head>
<body class="h-[100dvh] flex items-center justify-center p-0 sm:p-4 md:p-8 relative">

    <!-- ========== POPUP ผู้พัฒนา ========== -->
    <div id="credit-popup" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300">
        <div id="popup-content" class="bg-white rounded-[2rem] p-6 md:p-8 max-w-sm w-full shadow-2xl transform scale-95 transition-transform duration-300">
            <div class="text-center">
                <!-- Avatar -->
                <div class="w-20 h-20 bg-gradient-to-br from-blue-50 to-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner border-4 border-white overflow-hidden ring-2 ring-blue-100">
                    <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" alt="rw image" class="w-full h-full object-cover">
                </div>

                <h2 class="text-2xl font-bold text-gray-800 mb-1 tracking-tight">Chatbot RW-AI</h2>
                <div class="w-10 h-1.5 bg-blue-500 mx-auto rounded-full mb-5 opacity-80"></div>

                <p class="text-sm md:text-base text-gray-500 mb-5 leading-relaxed">
                    พัฒนาโดย ศิษย์เก่า<br>
                    <strong class="text-gray-800 text-lg font-semibold">นาย ณัฏฐพัชร อินแสงจันทร์</strong><br>
                    <span class="inline-block mt-2 px-4 py-1.5 bg-blue-50 text-blue-600 rounded-full text-sm font-bold tracking-wide">รุ่น 78</span>
                </p>

                <!-- IG Link -->
                <a href="https://instagram.com/n19axor_" target="_blank" class="inline-flex items-center justify-center gap-2 mb-5 px-5 py-2.5 bg-gradient-to-r from-pink-50 to-purple-50 hover:from-pink-100 hover:to-purple-100 text-pink-600 rounded-xl transition-all duration-200 shadow-sm hover:shadow-md group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="group-hover:scale-110 transition-transform"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                    <span class="text-sm font-semibold tracking-wide">IG: @n19axor_</span>
                </a>

                <!-- TOS Box -->
                <a href="privacy_policy.php" id="tos-link" class="block pointer-events-none opacity-50 transition-opacity duration-300">
                    <div id="tos-box" class="text-left bg-gray-50 p-4 rounded-xl border border-gray-200 mb-5 text-xs text-gray-600 h-28 overflow-y-auto shadow-inner relative group cursor-pointer hover:bg-gray-100 transition-colors">
                        <p class="font-bold text-gray-800 mb-2 flex justify-between items-center">
                            ข้อตกลงการใช้งาน (TOS)
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-500"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </p>
                        <p class="text-gray-500 leading-relaxed">
                            กรุณาเลื่อนลงเพื่ออ่านรายละเอียดข้อตกลงฉบับเต็ม เมื่ออ่านจบแล้วระบบจะเปิดให้กดยอมรับเงื่อนไขด้านล่างครับ...<br><br>
                            (ข้อกำหนด: ระบบแชทบอทนี้พัฒนาเพื่ออำนวยความสะดวกในการค้นหาข้อมูล ข้อมูลอาจมีการเปลี่ยนแปลง โปรดตรวจสอบกับทางโรงเรียนอีกครั้ง)
                        </p>
                        <!-- Gradient fade effect at bottom of scroll -->
                        <div class="absolute bottom-0 left-0 w-full h-8 bg-gradient-to-t from-gray-50 to-transparent pointer-events-none rounded-b-xl group-hover:from-gray-100"></div>
                    </div>
                </a>

                <label class="flex items-center justify-center gap-3 mb-6 cursor-pointer group">
                    <div class="relative flex items-center">
                        <input type="checkbox" id="tos-checkbox" class="peer w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer transition-all">
                    </div>
                    <span class="text-sm font-medium text-gray-600 peer-checked:text-blue-700 transition-colors select-none">
                        ฉันอ่านและยอมรับข้อตกลง
                    </span>
                </label>

                <button id="start-btn" disabled class="w-full bg-gray-200 text-gray-400 cursor-not-allowed font-semibold py-3.5 rounded-xl transition-all duration-300 text-sm tracking-wide uppercase">
                    เริ่มต้นใช้งาน
                </button>
            </div>
        </div>
    </div>

    <!-- ========== MAIN CHAT APP ========== -->
    <div class="bg-white/80 backdrop-blur-xl w-full h-full sm:h-[90vh] sm:max-w-xl md:max-w-2xl lg:max-w-3xl sm:rounded-[2rem] shadow-2xl flex flex-col overflow-hidden relative border border-white/50 transition-all duration-300">
        
        <!-- Header (Glassmorphism) -->
        <header class="bg-white/90 backdrop-blur-md p-4 px-5 flex items-center gap-4 relative z-20 shadow-[0_4px_20px_-10px_rgba(0,0,0,0.1)] border-b border-gray-100 no-select">
            <div class="relative shrink-0">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm border border-gray-100 overflow-hidden p-0.5">
                    <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" alt="RW-AI Logo" class="w-full h-full object-cover rounded-full">
                </div>
                <!-- Online Badge -->
                <div class="absolute bottom-0.5 right-0.5 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></div>
            </div>
            <div class="flex-1">
                <h1 class="font-bold text-gray-800 text-lg md:text-xl leading-tight tracking-tight">RW-AI</h1>
                <p id="ai-status" class="text-xs text-gray-500 font-medium flex items-center gap-1.5 mt-0.5">
                    <span class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></span>
                    กำลังโหลดระบบ...
                </p>
            </div>
            <button onclick="openPopup()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors bg-gray-50 border border-gray-100" type="button" title="ข้อมูลผู้พัฒนา">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </button>
        </header>

        <!-- Chat Container -->
        <main id="chat-container" class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6 scroll-smooth bg-[#f8fafc]/50">
            <!-- ข้อความต้อนรับ -->
            <div class="flex justify-start msg-animate origin-top-left">
                <div class="w-9 h-9 rounded-full flex items-center justify-center mr-3 flex-shrink-0 self-end mb-5 border border-gray-200 bg-white shadow-sm overflow-hidden p-0.5">
                    <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" alt="RW-AI" class="w-full h-full object-cover rounded-full">
                </div>
                <div class="flex flex-col items-start w-full max-w-[85%] md:max-w-[75%]">
                    <div class="bg-white text-gray-800 p-4 rounded-2xl rounded-bl-sm shadow-[0_2px_10px_-4px_rgba(0,0,0,0.1)] border border-gray-100 text-sm md:text-base leading-relaxed ai-content w-full">
                        <p>สวัสดีครับน้องๆ 👋 พี่ <strong>RW-AI</strong> (รุ่น 78) ยินดีให้บริการครับ</p>
                        <p>มีอะไรอยากสอบถามเกี่ยวกับโรงเรียนฤทธิยะวรรณาลัย พิมพ์ถามพี่มาได้เลยนะครับ!</p>
                    </div>
                    <span class="text-[10px] md:text-xs text-gray-400 mt-1.5 ml-1 font-medium">เพิ่งใช้งาน</span>
                </div>
            </div>
        </main>

        <!-- Step Indicator (Typing) -->
        <div id="step-indicator" class="hidden px-4 md:px-6 pb-2 bg-gradient-to-t from-white to-transparent pt-4 relative z-10">
            <div class="flex justify-start msg-animate origin-bottom-left">
                <div class="w-9 h-9 rounded-full flex items-center justify-center mr-3 flex-shrink-0 border border-gray-200 bg-white shadow-sm overflow-hidden p-0.5">
                    <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" alt="RW-AI" class="w-full h-full object-cover rounded-full">
                </div>
                <div class="bg-white p-3 px-5 rounded-2xl rounded-bl-sm shadow-sm border border-gray-100 flex gap-3 items-center">
                    <div class="typing-dots flex items-center h-full pt-1">
                        <span></span><span></span><span></span>
                    </div>
                    <span id="step-text" class="text-xs text-gray-500 font-medium">พี่ RW-AI กำลังคิด...</span>
                </div>
            </div>
        </div>

        <!-- Footer / Input Area -->
        <footer class="bg-white border-t border-gray-100 z-20 pb-safe shadow-[0_-10px_30px_-15px_rgba(0,0,0,0.05)] relative">
            
            <!-- Suggestions Chips -->
            <div id="suggestions" class="flex overflow-x-auto px-4 py-3 gap-2 no-select scrollbar-hide">
                <button onclick="useSuggestion('ระเบียบการแต่งกายนักเรียนเป็นอย่างไร?')" class="whitespace-nowrap px-4 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-full text-xs md:text-sm hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50 transition-all shadow-sm shrink-0 font-medium">👕 ระเบียบแต่งกาย</button>
                <button onclick="useSuggestion('ขอเนื้อเพลงมาร์ชโรงเรียน')" class="whitespace-nowrap px-4 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-full text-xs md:text-sm hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50 transition-all shadow-sm shrink-0 font-medium">🎵 เพลงโรงเรียน</button>
                <button onclick="useSuggestion('จ่ายค่าเทอมได้ที่ไหน?')" class="whitespace-nowrap px-4 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-full text-xs md:text-sm hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50 transition-all shadow-sm shrink-0 font-medium">💰 จ่ายค่าเทอม</button>
                <button onclick="useSuggestion('ตารางเวลาเรียนและพักกลางวัน')" class="whitespace-nowrap px-4 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-full text-xs md:text-sm hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50 transition-all shadow-sm shrink-0 font-medium">⏰ เวลาเรียน</button>
                <button onclick="useSuggestion('ติดต่อโรงเรียนทางไหนได้บ้าง?')" class="whitespace-nowrap px-4 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-full text-xs md:text-sm hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50 transition-all shadow-sm shrink-0 font-medium">📞 ติดต่อโรงเรียน</button>
            </div>

            <!-- Input Box -->
            <div class="px-4 pb-4 md:px-6 md:pb-6 pt-1">
                <div class="flex items-end gap-2 bg-gray-50 border border-gray-200 rounded-3xl p-1.5 pl-4 focus-within:ring-2 focus-within:ring-blue-100 focus-within:bg-white focus-within:border-blue-300 transition-all shadow-inner relative">
                    <textarea id="user-input"
                        placeholder="พิมพ์ถามพี่ RW-AI ได้เลย..."
                        rows="1"
                        class="flex-1 resize-none bg-transparent py-3 focus:outline-none text-sm md:text-base text-gray-700 placeholder-gray-400 max-h-32 overflow-y-auto"
                        autocomplete="off"></textarea>
                    
                    <button type="button" onclick="sendMessage()" id="send-btn"
                        class="bg-gradient-to-r from-blue-600 to-blue-500 text-white w-10 h-10 md:w-11 md:h-11 rounded-full flex items-center justify-center hover:shadow-lg hover:shadow-blue-500/30 active:scale-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed shrink-0 mb-0.5 mr-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="translate-x-[1px] -translate-y-[1px]">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
                
                <p class="text-[10px] text-gray-400 text-center mt-3 font-light">
                    ข้อมูลประมวลผลโดย AI ศิษย์เก่า (รุ่น 78) อาจมีข้อผิดพลาด โปรดตรวจสอบอีกครั้ง
                </p>
            </div>
        </footer>
    </div>

    <!-- ========== IMAGE MODAL ========== -->
    <div id="image-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/90 opacity-0 pointer-events-none transition-opacity duration-300 backdrop-blur-sm" onclick="closeImageModal()">
        <button class="absolute top-6 right-6 text-white bg-white/10 hover:bg-white/20 p-2.5 rounded-full transition-colors backdrop-blur-md">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
        <img id="modal-img" src="" class="max-w-[95%] max-h-[85dvh] rounded-xl shadow-2xl object-contain scale-95 transition-transform duration-300" alt="Full Preview" onclick="event.stopPropagation()">
    </div>

    <!-- ========== SCRIPTS ========== -->
    <script>
        // 1. ตั้งค่า Marked.js
        marked.setOptions({ breaks: true, gfm: true });

        // 2. ตัวแปร DOM
        const inputField = document.getElementById('user-input');
        const container = document.getElementById('chat-container');
        const stepIndicator = document.getElementById('step-indicator');
        const stepText = document.getElementById('step-text');
        const sendBtn = document.getElementById('send-btn');
        const aiStatus = document.getElementById('ai-status');

        // 3. Popup & TOS Logic
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
            setTimeout(() => inputField.focus(), 300);
        }

        const box = document.getElementById('tos-box');
        const linkBox = document.getElementById('tos-link');
        const checkbox = document.getElementById('tos-checkbox');
        const btn = document.getElementById('start-btn');
        let unlocked = false;

        box.addEventListener('scroll', () => {
            if (box.scrollTop + box.clientHeight >= box.scrollHeight - 5) {
                unlocked = true;
                linkBox.classList.remove('pointer-events-none', 'opacity-50');
            }
        });

        linkBox.addEventListener('click', () => {
            if (unlocked) window.location.href = 'privacy_policy.php';
        });

        checkbox.addEventListener('change', () => {
            if (checkbox.checked) {
                btn.disabled = false;
                btn.className = "w-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-700 hover:to-blue-600 text-white font-bold py-3.5 rounded-xl transition-all shadow-md active:scale-[0.98] duration-200 cursor-pointer text-sm tracking-wide uppercase";
            } else {
                btn.disabled = true;
                btn.className = "w-full bg-gray-200 text-gray-400 cursor-not-allowed font-semibold py-3.5 rounded-xl transition-all duration-300 text-sm tracking-wide uppercase";
            }
        });

        btn.addEventListener('click', () => {
            if (checkbox.checked) closePopup();
        });

        // 4. Chat UI Logic
        aiStatus.innerHTML = '<span class="w-2 h-2 bg-green-500 rounded-full shadow-[0_0_8px_#22c55e]"></span> <span class="text-green-600">พร้อมช่วยเหลือแล้ว</span>';

        function escapeHtml(text) {
            return String(text).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
        }

        function scrollToBottom() {
            requestAnimationFrame(() => container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' }));
        }

        function appendMessage(message, isUser = true) {
            const time = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
            let msgHtml = '';

            if (isUser) {
                msgHtml = `
                <div class="flex justify-end msg-animate origin-bottom-right">
                    <div class="flex flex-col items-end w-full">
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-3.5 px-4 md:p-4 rounded-[1.25rem] rounded-br-[0.25rem] shadow-md max-w-[85%] md:max-w-[75%] text-sm md:text-base leading-relaxed msg-text border border-blue-400/30">
                            ${escapeHtml(message).replace(/\n/g, '<br>')}
                        </div>
                        <span class="text-[10px] md:text-xs text-gray-400 mt-1 mr-1.5 font-medium">${time}</span>
                    </div>
                </div>`;
            } else {
                msgHtml = `
                <div class="flex justify-start msg-animate origin-bottom-left">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center mr-3 flex-shrink-0 self-end mb-5 border border-gray-200 bg-white shadow-sm overflow-hidden p-0.5">
                        <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" alt="RW-AI" class="w-full h-full object-cover rounded-full">
                    </div>
                    <div class="flex flex-col items-start w-full max-w-[85%] md:max-w-[75%]">
                        <div class="bg-white text-gray-800 p-4 px-5 rounded-[1.25rem] rounded-bl-[0.25rem] shadow-[0_2px_10px_-4px_rgba(0,0,0,0.1)] border border-gray-100 text-sm md:text-base leading-relaxed ai-content w-full">
                            ${marked.parse(message)}
                        </div>
                        <span class="text-[10px] md:text-xs text-gray-400 mt-1.5 ml-1 font-medium">${time}</span>
                    </div>
                </div>`;
            }
            container.insertAdjacentHTML('beforeend', msgHtml);
            scrollToBottom();
        }

        function autoResizeTextarea() {
            inputField.style.height = 'auto';
            inputField.style.height = Math.min(inputField.scrollHeight, 120) + 'px';
        }

        const processingSteps = [
            { text: "กำลังค้นหาข้อมูลจากฐานความรู้...", minTime: 600 },
            { text: "พี่กำลังพิมพ์คำตอบนะครับ...", minTime: 800 }
        ];

        async function animateSteps() {
            for (let step of processingSteps) {
                stepText.innerText = step.text;
                await new Promise(r => setTimeout(r, step.minTime));
            }
        }

        async function sendMessage() {
            const message = inputField.value.trim(); 
            if (!message) return;

            inputField.value = '';
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
                    }).catch(() => { throw new Error("Network Error") }),
                    animateSteps() 
                ]);

                const text = await apiResponse.text();
                const data = JSON.parse(text);

                stepIndicator.classList.add('hidden');
                appendMessage(data.response || 'AI ไม่ตอบกลับ', false);

            } catch (error) {
                console.error(error);
                stepText.innerText = "เกิดข้อผิดพลาดในการเชื่อมต่อ";
                stepText.classList.replace("text-gray-500", "text-red-500");
                setTimeout(() => {
                    stepIndicator.classList.add('hidden');
                    stepText.classList.replace("text-red-500", "text-gray-500");
                    appendMessage('ขออภัยครับ ระบบเชื่อมต่อล้มเหลว หรือน้องถามเร็วเกินไป ลองใหม่อีกครั้งนะครับ', false);
                }, 1500);
            } finally {
                inputField.disabled = false;
                sendBtn.disabled = false;
                inputField.focus();
                scrollToBottom();
            }
        }

        function useSuggestion(text) {
            if (inputField.disabled) return;
            inputField.value = text;
            autoResizeTextarea();
            sendMessage();
        }

        inputField.addEventListener('input', autoResizeTextarea);
        inputField.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // 5. Image Modal Logic
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

        function closeImageModal() {
            const modal = document.getElementById('image-modal');
            const modalImg = document.getElementById('modal-img');
            modalImg.classList.remove('scale-100');
            modalImg.classList.add('scale-95');
            modal.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => modalImg.src = '', 300);
        }

        container.addEventListener('click', e => {
            if (e.target.tagName === 'IMG' && e.target.closest('.ai-content')) {
                openImageModal(e.target.src);
            }
        });

        window.onload = () => {
            setTimeout(openPopup, 300); 
            autoResizeTextarea();
        };
    </script>
</body>
</html>

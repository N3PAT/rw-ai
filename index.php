<?php
if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json');
    $url = "https://rwai1.statuspage.io/api/v2/summary.json";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    echo $response;
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0056b3">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="icon-192x192.png">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>RW-AI Chatbot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  

<style>
    /* Base & Theme Styles */
    body {
        font-family: 'Sarabun', sans-serif;
        background: linear-gradient(135deg, #e0e7ff 0%, #f3f4f6 100%);
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        transition: background 0.5s ease;
    }

    body.dark-mode {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #f1f5f9;
    }

    /* Dark Mode Utility Overrides */
    body.dark-mode .bg-\[\#f8fafc\] { background-color: #0f172a !important; border-color: #1e293b !important; }
    body.dark-mode .bg-white { background-color: #1e293b !important; color: #f1f5f9 !important; }
    body.dark-mode .text-gray-800 { color: #f1f5f9 !important; }
    body.dark-mode .text-gray-600 { color: #cbd5e1 !important; }
    body.dark-mode .text-gray-700 { color: #e2e8f0 !important; }
    body.dark-mode .bg-gray-50 { background-color: #334155 !important; border-color: #475569 !important; }
    body.dark-mode .bg-gray-200 { background-color: #334155 !important; }
    body.dark-mode .border-gray-100, 
    body.dark-mode .border-gray-200 { border-color: #334155 !important; }
    
    body.dark-mode .ai-content { background-color: #334155 !important; border-color: #475569 !important; color: #f1f5f9 !important; }
    body.dark-mode .bg-blue-50 { background-color: #1e293b !important; color: #60a5fa !important; border-color: #2563eb !important; }

    /* Theme Toggle Icons */
    .sun-icon { display: none; }
    .moon-icon { display: block; }
    body.dark-mode .sun-icon { display: block; }
    body.dark-mode .moon-icon { display: none; }

    /* Inputs & Selection */
    input, textarea {
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
        user-select: text;
    }
    
    .no-select {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* Animations */
    @keyframes messagePop {
        0% { opacity: 0; transform: scale(0.8) translateY(20px); }
        70% { transform: scale(1.05) translateY(-2px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .msg-animate {
        animation: messagePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }

    /* Chat Messages */
    .msg-text {
        white-space: pre-wrap;
        word-break: normal;          
        overflow-wrap: anywhere;    
        display: inline-block;      
        text-align: left;            
    }

    .feedback-btn {
        transition: all 0.2s ease;
        opacity: 0;
        animation: fadeIn 0.5s ease 0.5s forwards;
    }

    /* Image Modal */
    #image-modal { transition: opacity 0.3s ease; }
    #modal-img { transition: transform 0.3s ease; }
    .ai-content img { cursor: zoom-in; transition: opacity 0.2s; }
    .ai-content img:hover { opacity: 0.9; }

    /* Toast Notifications */
    #toast-container {
        position: fixed;
        top: 24px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 10000;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        width: auto;
        min-width: 280px;
        max-width: 90vw;
        pointer-events: none;
    }

    .toast {
        pointer-events: auto;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 18px;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.5);
        color: #334155;
        font-size: 14px;
        font-weight: 500;
        opacity: 0;
        transform: translateY(-20px) scale(0.9);
        transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .toast.show {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    .toast-offline { border-bottom: 2px solid #ef4444; }
    .toast-online { border-bottom: 2px solid #10b981; }
    .toast-info { border-bottom: 2px solid #3b82f6; }

    .toast-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
    }

    .toast-offline svg { color: #f87171; }
    .toast-online svg { color: #34d399; }
    .toast-info svg { color: #60a5fa; }

    body.dark-mode .toast {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #f1f5f9;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
    }

    /* Typing Indicator */
    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .typing-indicator span {
        width: 6px;
        height: 6px;
        background-color: #3b82f6;
        border-radius: 50%;
        animation: typing 1.4s infinite ease-in-out both;
    }
    
    .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
    .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
    
    @keyframes typing {
        0%, 80%, 100% { transform: scale(0); opacity: 0.5; }
        40% { transform: scale(1); opacity: 1; }
    }

    body.dark-mode .typing-indicator span { background-color: #60a5fa; }
    body.dark-mode #step-text { color: #93c5fd; }

    .typing-cursor {
        display: inline-block;
        width: 8px;
        height: 1em;
        background-color: #3b82f6;
        vertical-align: middle;
        margin-left: 2px;
        animation: blink 1s step-end infinite;
    }
    
    body.dark-mode .typing-cursor { background-color: #60a5fa; }
    
    @keyframes blink { 50% { opacity: 0; } }

    /* AI Content & Links */
    .ai-content a {
        display: inline-flex;
        align-items: center;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); 
        color: #ffffff !important;
        padding: 8px 18px;
        border-radius: 9999px; 
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        margin: 8px 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        word-break: keep-all; 
    }
            
    .ai-content a:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 15px -3px rgba(59, 130, 246, 0.4);
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    }

    .ai-content a::after {
        content: "↗";
        font-size: 16px;
        margin-left: 6px;
        font-weight: bold;
    }

    body.dark-mode .ai-content a {
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
    }
    
    body.dark-mode .ai-content a:hover {
        background: linear-gradient(135deg, #4338ca 0%, #2563eb 100%);
    }

    /* Lists */
    .ai-content ul {
        list-style-type: disc;
        margin-left: 1.5rem;
        margin-bottom: 1rem;
    }

    .ai-content ol {
        list-style-type: decimal;
        margin-left: 1.5rem;
        margin-bottom: 1rem;
    }

    .ai-content li {
        margin-bottom: 0.25rem;
        display: list-item; 
    }
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
            <p class="text-sm md:text-base text-gray-600 mb-4">
                พัฒนาโดย ศิษย์เก่า<br>
                <strong class="text-gray-800 text-lg">นาย ณัฏฐพัชร อินแสงจันทร์</strong><br>
                <span class="inline-block mt-1 px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm font-medium border border-blue-100">รุ่น 78</span>
            </p>

            <div class="mb-6">
                <a href="https://www.instagram.com/n19axor_?igsh=NmM5eHRyaTBubmUy" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-purple-500 via-pink-500 to-orange-400 text-white rounded-xl text-sm font-medium transition-transform hover:scale-105 active:scale-95 shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z"/>
                    </svg>
                    <span>n19axor_</span>
                </a>
            </div>
            
            <label class="flex items-center justify-center gap-2 mb-5 cursor-pointer group">
                <a href="นโยบายการใช้เทคโนโลยี Generative AI ที่ยอมรับได้ (2).pdf" id="tos-link" class="block">
                    <div id="tos-box" class="text-left bg-gray-50 p-3 rounded-lg border border-gray-200 mb-4 text-xs text-gray-600 h-24 overflow-y-auto shadow-inner cursor-pointer hover:bg-gray-100 transition">
                        <p class="font-bold text-gray-800 mb-2">ข้อตกลงการใช้งาน (Terms of Service)</p>
                        <p class="text-gray-600">กรุณาคลิกเพื่ออ่านรายละเอียดข้อตกลงฉบับเต็ม</p>
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
    <header class="bg-blue-600/95 backdrop-blur-md p-4 text-white flex items-center gap-3 relative z-20 shadow-md no-select transition-all duration-500 dark:bg-slate-900/95">
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

        <button id="dark-mode-toggle" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors outline-none">
            <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        </button>

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

    <div id="step-indicator" class="hidden px-4 md:px-6 pb-2 transition-all duration-300">
        <div class="flex justify-start items-center gap-3 bg-blue-50/80 p-3 py-2.5 rounded-2xl w-fit border border-blue-100/50 shadow-sm msg-animate">
            <div class="typing-indicator">
                <span></span><span></span><span></span>
            </div>
            <span id="step-text" class="text-xs md:text-sm text-blue-700 font-medium">พี่ RW-AI กำลังพิมพ์...</span>
        </div>
    </div>

    <footer class="p-3 sm:p-4 md:p-5 bg-white border-t border-gray-100 z-10 shadow-[0_-4px_15px_-3px_rgba(0,0,0,0.05)]">
        <div id="suggestions" class="flex overflow-x-auto pb-3 gap-2 no-select scrollbar-hide">
            <button onclick="useSuggestion('ระเบียบการแต่งกายนักเรียนเป็นอย่างไร?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs hover:bg-blue-100 hover:scale-105 transition-all duration-200">ระเบียบการแต่งกาย</button>
            <button onclick="useSuggestion('ขอแผนผังโรงเรียนหน่อยได้ไหม?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs hover:bg-blue-100 hover:scale-105 transition-all duration-200">แผนผังโรงเรียน</button>
            <button onclick="useSuggestion('ขอเนื้อเพลงมาร์ช​โรงเรียนหน่อย?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs hover:bg-blue-100 hover:scale-105 transition-all duration-200">เพลงมาร์ชโรงเรียน</button>
            <button onclick="useSuggestion('สิ่งศักดิ์สิทธิ์​ประจำ​โรงเรียนคืออะไร?')" class="whitespace-nowrap px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full text-xs hover:bg-blue-100 hover:scale-105 transition-all duration-200">สิ่งศักดิ์สิทธิ์​ประจำ​โรงเรียน</button>
        </div>

        <div id="input-container" class="flex items-end gap-2 bg-gray-50 border border-gray-200 rounded-3xl p-2 focus-within:ring-2 focus-within:ring-blue-400 focus-within:bg-white focus-within:shadow-md transition-all duration-300 dark:bg-slate-800 dark:border-slate-700">
            <textarea id="user-input" placeholder="พิมพ์คำถามที่นี่..." rows="1" class="flex-1 resize-none bg-transparent px-3 py-2 focus:outline-none text-sm md:text-base text-gray-700 max-h-32"></textarea>
            
            <button type="button" onclick="sendMessage()" id="send-btn" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white w-10 h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center hover:from-blue-600 hover:to-blue-700 hover:rotate-12 active:scale-90 transition-all shadow-md shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
    </footer>
</div>

<script>
const renderer = new marked.Renderer();
const originalLink = renderer.link.bind(renderer); 

renderer.link = function(href, title, text) {
    if (href && href.match(/\.(mp4|webm|ogg)$/i)) {
        return `
        <div class="my-3 flex flex-col items-start w-full transition-all duration-300">
            <video controls playsinline preload="metadata" class="w-full max-h-[250px] md:max-h-[300px] object-contain rounded-xl border border-gray-200 shadow-sm bg-black/5 mb-2 dark:border-slate-600 dark:bg-slate-800">
                <source src="${href}" type="video/mp4">
                เบราว์เซอร์ของคุณไม่รองรับการเล่นวิดีโอนี้
            </video>
            <a href="${href}" target="_blank" title="${title || ''}" class="!text-xs">ดูแบบเต็มจอ (${text})</a>
        </div>`;
    }
    
    return originalLink(href, title, text);
};

marked.setOptions({ renderer: renderer, breaks: true, gfm: true });

// ลบ const inputField ที่ซ้ำออกไป 1 บรรทัด
const inputField = document.getElementById('user-input');
const container = document.getElementById('chat-container');
const stepIndicator = document.getElementById('step-indicator');
const stepText = document.getElementById('step-text');
const sendBtn = document.getElementById('send-btn');
const aiStatus = document.getElementById('ai-status');


// --- 1. เพิ่มตัวแปรเก็บความจำของแชท ---
let chatHistory = [];

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

async function sendFeedback(logId, rating, btnElement) {
    if (!logId) return;
    const parent = btnElement.parentElement;
    parent.innerHTML = '<span class="text-[10px] text-blue-500 animate-pulse">ขอบคุณสำหรับ Feedback ครับ!</span>';

    try {
        await fetch('update_feedback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ log_id: logId, rating: rating })
        });
    } catch (e) { console.error("Feedback Error:", e); }
}

// --- 2. ปรับฟังก์ชันแสดงข้อความ (ใช้เฉพาะของ User) ---
function appendMessage(message, isUser = true) {
    const time = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    if (isUser) {
        const msgHtml = `
        <div class="flex justify-end msg-animate w-full">
            <div class="flex flex-col items-end max-w-[85%]">
                <div class="bg-blue-600 text-white p-3.5 px-4 rounded-2xl rounded-br-none shadow-md text-sm md:text-base msg-text">${message}</div>
                <span class="text-[10px] text-gray-400 mt-1 mr-1">${time}</span>
            </div>
        </div>`;
        container.insertAdjacentHTML('beforeend', msgHtml);
        scrollToBottom();
    }
}

// --- 3. ฟังก์ชันใหม่: เอฟเฟกต์พิมพ์ข้อความของบอท ---
function typeWriterEffect(fullText, logId) {
    const time = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    const uniqueId = 'msg-' + Date.now();

    // สร้างโครงสร้างข้อความเปล่าๆ ไว้ก่อน
    const msgHtml = `
    <div class="flex justify-start msg-animate">
        <div class="w-8 h-8 md:w-10 md:h-10 rounded-full mr-2 flex-shrink-0 self-end mb-5 border border-blue-200 overflow-hidden">
            <img src="https://taothetutor.wordpress.com/wp-content/uploads/2026/04/rw_20260412_025152_00002443189004229283520.png" class="w-full h-full object-cover">
        </div>
        <div class="flex flex-col items-start max-w-[85%] w-full">
            <div id="${uniqueId}" class="bg-white text-gray-800 p-3.5 px-4 rounded-2xl rounded-bl-none shadow-sm border border-gray-100 text-sm md:text-base leading-relaxed ai-content w-full min-h-[40px]">
            </div>
            <div id="feedback-${uniqueId}" class="hidden"></div>
            <span class="text-[10px] text-gray-400 mt-1 ml-1">${time}</span>
        </div>
    </div>`;

    container.insertAdjacentHTML('beforeend', msgHtml);
    scrollToBottom();

    const textContainer = document.getElementById(uniqueId);
    const feedbackContainer = document.getElementById(`feedback-${uniqueId}`);
    let i = 0;
    let currentText = '';

    // ลูปพิมพ์ทีละตัวอักษร
    const typingInterval = setInterval(() => {
        // พิมพ์ทีละ 2-3 ตัวอักษรให้ดูเป็นธรรมชาติ (ไม่ช้าไป)
        const charsToAdd = 2; 
        currentText += fullText.substring(i, i + charsToAdd);
        i += charsToAdd;

        // แปลง Markdown ไปพร้อมๆ กับการพิมพ์
        textContainer.innerHTML = marked.parse(currentText) + '<span class="typing-cursor"></span>';
        scrollToBottom();

        // พิมพ์เสร็จแล้ว
        if (i >= fullText.length) {
            clearInterval(typingInterval);
            textContainer.innerHTML = marked.parse(fullText); // เอา cursor ออก

            // แสดงปุ่ม Feedback
            if (logId) {
                feedbackContainer.innerHTML = `
                <div class="flex gap-2 mt-2 feedback-btn">
                    <button onclick="sendFeedback(${logId}, 1, this)" class="p-1 px-2 rounded-lg border border-gray-100 hover:bg-blue-50 hover:text-blue-600 transition-colors text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                    </button>
                    <button onclick="sendFeedback(${logId}, -1, this)" class="p-1 px-2 rounded-lg border border-gray-100 hover:bg-red-50 hover:text-red-600 transition-colors text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"></path></svg>
                    </button>
                </div>`;
                feedbackContainer.classList.remove('hidden');
            }
        }
    }, 10); // ความเร็วในการพิมพ์ (10 มิลลิวินาที)
}

// --- 4. ปรับฟังก์ชัน Send ให้ส่งและจำประวัติได้ ---
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

    chatHistory.push({ role: 'user', parts: [{ text: message }] });
    if (chatHistory.length > 6) chatHistory.shift(); 

    try {
        const response = await fetch('chat_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                message: message,
                history: chatHistory
            })
        });
        
        const data = await response.json();
        stepIndicator.classList.add('hidden');
        
        const botReply = data.response || 'พี่ขอโทษครับ ระบบขัดข้องชั่วคราว';

        chatHistory.push({ role: 'model', parts: [{ text: botReply }] });
        if (chatHistory.length > 6) chatHistory.shift();

        typeWriterEffect(botReply, data.log_id);

    } catch (error) {
        stepIndicator.classList.add('hidden');
        typeWriterEffect('ระบบเชื่อมต่อล้มเหลว หรือน้องถามเร็วเกินไปครับ ลองใหม่อีกครั้งนะ', null);
    } finally {
        inputField.disabled = false;
        sendBtn.disabled = false;
        
        // ตรวจสอบว่าไม่ใช่บนมือถือ ถึงจะให้ Focus (เพื่อไม่ให้แป้นพิมพ์เด้งบนโทรศัพท์)
        if (!/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            inputField.focus();
        }
    }
}


function useSuggestion(text) {
    if (inputField.disabled || sendBtn.disabled) return; // ป้องกันการกดซ้ำตอนบอทกำลังคิด
    inputField.value = text;
    sendMessage();
}


function autoResizeTextarea() {
    inputField.style.height = 'auto';
    inputField.style.height = Math.min(inputField.scrollHeight, 128) + 'px';
}

inputField.addEventListener('input', autoResizeTextarea);

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
            btn.disabled = false;
            btn.className = "w-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-700 hover:to-blue-600 text-white font-medium py-3 rounded-xl transition-all shadow-md hover:shadow-lg active:scale-[0.98] duration-300 cursor-pointer";
        } else {
            btn.disabled = true;
            btn.className = "w-full bg-gray-200 text-gray-400 cursor-not-allowed font-medium py-3 rounded-xl transition-all duration-300";
        }
    }

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

<script>
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
    
    setTimeout(() => {
        modalImg.src = '';
    }, 300);
}

container.addEventListener('click', function(e) {
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

<div id="toast-container"></div>

<script>
    const toggleBtn = document.getElementById('dark-mode-toggle');
    const body = document.body;

    if (localStorage.getItem('theme-mode') === 'dark') {
        body.classList.add('dark-mode');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            localStorage.setItem('theme-mode', isDark ? 'dark' : 'light');
        });
    }

    function showToast(message, type = 'info', duration = 4000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type} no-select`;

        const icons = {
            offline: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`,
            online: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`,
            info: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`
        };

        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-message">${message}</div>
        `;
        
        container.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 500);
        }, duration);
    }

    window.addEventListener('offline', () => {
        showToast('ขาดการเชื่อมต่ออินเทอร์เน็ต พี่ RW-AI อาจตอบช้าลงนะครับ', 'offline', 5000);
    });

    window.addEventListener('online', () => {
        showToast('กลับมาเชื่อมต่อแล้ว! ถามพี่ RW-AI ต่อได้เลยครับ', 'online', 3000);
    });

    if (!navigator.onLine) {
        showToast('ขณะนี้คุณกำลังใช้งานแบบออฟไลน์', 'offline', 5000);
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const swUrl = 'sw.js?v=<?php echo filemtime("sw.js"); ?>';
            
            navigator.serviceWorker.register(swUrl)
                .then(reg => {
                    console.log('RW-AI PWA Ready!');
                    reg.update();

                    reg.onupdatefound = () => {
                        const installingWorker = reg.installing;
                        if (installingWorker) {
                            installingWorker.onstatechange = () => {
                                if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    console.log('New version found! Reloading...');
                                    window.location.reload();
                                }
                            };
                        }
                    };
                })
                .catch(err => console.error('PWA Error:', err));
        });
    }
</script>
<script>
// ตัวแปรเก็บสถานะล่าสุดเพื่อเช็คการเปลี่ยนแปลง
let lastKnownStatus = {};

async function checkSystemHealth() {
    try {
        const response = await fetch('?action=check_status');
        const data = await response.json();
        
        const aiStatusText = document.getElementById('ai-status');
        
        // 1. ตรวจสอบสถานะภาพรวม (Indicator)
        if (data.status.indicator === 'none') {
            aiStatusText.innerHTML = '<span class="w-1.5 h-1.5 bg-green-400 rounded-full"></span> AI พร้อมใช้งานแล้ว';
        } else {
            aiStatusText.innerHTML = '<span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span> พบปัญหาในบางระบบ';
        }

        // 2. ตรวจสอบราย Component (API, Processing, Status)
        data.components.forEach(comp => {
            const currentStatus = comp.status;
            const previousStatus = lastKnownStatus[comp.id];

            // ถ้าสถานะเปลี่ยนจากปกติ (operational) เป็นอย่างอื่น
            if (currentStatus !== 'operational' && previousStatus === 'operational') {
                Swal.fire({
                    icon: 'error',
                    title: 'ระบบขัดข้อง',
                    text: `ตรวจพบว่า ${comp.name} กำลังมีปัญหาครับ`,
                    toast: true,
                    position: 'top-end', // แจ้งเตือนมุมขวาบน
                    showConfirmButton: false,
                    timer: 7000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
            }

            // ถ้าสถานะเปลี่ยนจากที่เคยล่ม กลับมาเป็นปกติ
            if (currentStatus === 'operational' && previousStatus && previousStatus !== 'operational') {
                Swal.fire({
                    icon: 'success',
                    title: 'ระบบกลับมาแล้ว',
                    text: `${comp.name} ใช้งานได้ปกติแล้วครับ`,
                    toast: true,
                    position: 'top-end', // แจ้งเตือนมุมขวาบน
                    showConfirmButton: false,
                    timer: 4000
                });
            }

            // อัปเดตสถานะล่าสุดเก็บไว้
            lastKnownStatus[comp.id] = currentStatus;
        });

    } catch (error) {
        console.error("Status Check Error:", error);
    }
}

// เริ่มต้นเช็คทันทีที่โหลดหน้า และเช็คซ้ำทุกๆ 60 วินาที
checkSystemHealth();
setInterval(checkSystemHealth, 60000);
</script>

</body>
</html>

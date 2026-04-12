<?php
// policy_page_rw_ai.php
$siteName = 'RW-AI Chatbot';
$updatedAt = '12 เมษายน 2569';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteName); ?> - Privacy Policy / Terms of Use</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #f3f4f6 100%);
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .glass {
            background: rgba(255,255,255,0.78);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .policy-content h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }
        .policy-content p, .policy-content li {
            color: #334155;
            line-height: 1.75;
        }
        .policy-content ul {
            list-style: disc;
            padding-left: 1.25rem;
            margin: 0.5rem 0 1rem;
        }
        .policy-content ol {
            list-style: decimal;
            padding-left: 1.25rem;
            margin: 0.5rem 0 1rem;
        }
        .tab-active {
            background: linear-gradient(90deg, #2563eb, #3b82f6);
            color: #fff;
            box-shadow: 0 10px 25px rgba(37,99,235,0.18);
        }
    </style>
</head>
<body class="min-h-[100dvh]">
    <div class="max-w-4xl mx-auto">
        <div class="glass border border-white/60 shadow-2xl  overflow-hidden">
            <header class="bg-gradient-to-r from-blue-700 to-blue-500 text-white p-5 sm:p-6 md:p-7">
                <div class="flex items-center gap-4">
<div class="w-14 h-14 rounded-2xl bg-white/15 border border-white/20 flex items-center justify-center shadow-lg">
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
        <line x1="10" y1="9" x2="8" y2="9"></line>
    </svg>
</div>
                    <div class="flex-1">
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold leading-tight"><?php echo htmlspecialchars($siteName); ?></h1>
                        <p class="text-blue-100 text-sm sm:text-base mt-1">Privacy Policy / Terms of Use</p>
                    </div>
                </div>
            </header>

            <div class="p-4 sm:p-6 md:p-7">
                <div class="flex flex-wrap gap-2 mb-5">
                    <button type="button" data-tab="privacy" class="policy-tab tab-active px-4 py-2 rounded-xl text-sm sm:text-base font-medium transition-all">
                        Privacy Policy
                    </button>
                    <button type="button" data-tab="terms" class="policy-tab bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-sm sm:text-base font-medium transition-all">
                        Terms of Use
                    </button>
                </div>

                <div class="grid gap-4 md:gap-6">
                    <section id="privacy" class="policy-panel block">
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 policy-content">
                            <div class="flex items-center gap-3 mb-4">
<div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
    </svg>
</div>
<div>
                                    <h2 class="!mt-0 !mb-1">นโยบายความเป็นส่วนตัว</h2>
                                    <p class="text-sm text-slate-500">อัปเดตล่าสุด: <?php echo htmlspecialchars($updatedAt); ?></p>
                                </div>
                            </div>

                            <p>
                                หน้านี้อธิบายแนวทางการใช้งานข้อมูลของผู้ใช้ในระบบ <?php echo htmlspecialchars($siteName); ?> โดยระบบนี้ไม่มีการเก็บข้อมูลการถามหรือข้อความสนทนาในฐานข้อมูลถาวร ข้อมูลที่ผู้ใช้ส่งเข้ามาจะถูกนำไปประมวลผลเพื่อสร้างคำตอบเท่านั้น และจะไม่ถูกนำไปใช้เกินกว่าวัตถุประสงค์ของการให้บริการ เว้นแต่ในกรณีที่จำเป็นต่อการตรวจสอบปัญหาทางเทคนิคหรือการทำงานของระบบ ผู้ใช้ควรใช้วิจารณญาณในการพิมพ์ข้อมูลต่างๆ และหลีกเลี่ยงการส่งข้อมูลส่วนตัวที่อ่อนไหวเข้าสู่ระบบโดยไม่จำเป็น
                            </p>

                            <h2>1) ข้อมูลที่อาจถูกใช้งาน</h2>
                            <ul>
                                <li>ข้อความที่ผู้ใช้พิมพ์เพื่อสนทนากับระบบในขณะนั้น</li>
                                <li>ข้อมูลเทคนิคพื้นฐาน เช่น เวลาเข้าใช้งาน ประเภทอุปกรณ์ และเบราว์เซอร์</li>
                                <li>ข้อมูลที่ผู้ใช้เลือกกรอกเพิ่มเติม หากมีการขอข้อมูลในส่วนอื่นของเว็บไซต์</li>
                            </ul>

                            <h2>2) วัตถุประสงค์ในการใช้ข้อมูล</h2>
                            <ul>
                                <li>เพื่อให้บริการตอบคำถามและทำงานของระบบ</li>
                                <li>เพื่อปรับปรุงคุณภาพ ความปลอดภัย และประสบการณ์ใช้งาน</li>
                                <li>เพื่อแก้ไขปัญหาและตรวจสอบข้อผิดพลาดของระบบ</li>
                            </ul>

                            <h2>3) การแชร์ข้อมูล</h2>
                            <p>
                                เราจะไม่ขายหรือเผยแพร่ข้อมูลส่วนตัวของผู้ใช้ให้บุคคลที่สาม ยกเว้นกรณีที่จำเป็นต่อการให้บริการ การปฏิบัติตามกฎหมาย หรือการปกป้องความปลอดภัยของระบบ
                            </p>

                            <h2>4) Cookies และตัวระบุการใช้งาน</h2>
                            <p>
                                ระบบอาจใช้คุกกี้หรือข้อมูลลักษณะเดียวกันเพื่อจำค่าการใช้งาน วิเคราะห์สถิติ และช่วยให้เว็บไซต์ทำงานได้ราบรื่นขึ้น
                            </p>

                            <h2>5) การเก็บรักษาข้อมูล</h2>
                            <p>
                                ข้อมูลจะถูกเก็บไว้เท่าที่จำเป็นต่อการให้บริการและปรับปรุงระบบ เมื่อไม่จำเป็นแล้วอาจถูกลบหรือทำให้ไม่สามารถระบุตัวตนได้
                            </p>

                            <h2>6) ความปลอดภัยของข้อมูล</h2>
                            <p>
                                เราพยายามใช้มาตรการที่เหมาะสมเพื่อป้องกันการเข้าถึง แก้ไข หรือเปิดเผยข้อมูลโดยไม่ได้รับอนุญาต อย่างไรก็ตาม ไม่มีระบบใดปลอดภัย 100%
                            </p>

                            <h2>7) สิทธิของผู้ใช้</h2>
                            <ul>
                                <li>ขอทราบว่ามีการเก็บข้อมูลอะไรบ้าง</li>
                                <li>ขอแก้ไข หรือลบข้อมูลในกรณีที่เหมาะสม</li>
                                <li>ถอนความยินยอมจากบางการใช้งานได้ตามเงื่อนไขของระบบ</li>
                            </ul>
                        </div>
                    </section>

                    <section id="terms" class="policy-panel hidden">
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 policy-content">
                            <div class="flex items-center gap-3 mb-4">
<div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 0 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
    </svg>
</div>
                                <div>
                                    <h2 class="!mt-0 !mb-1">ข้อตกลงการใช้งาน</h2>
                                    <p class="text-sm text-slate-500">อัปเดตล่าสุด: <?php echo htmlspecialchars($updatedAt); ?></p>
                                </div>
                            </div>

                            <p>
                                การใช้งาน <?php echo htmlspecialchars($siteName); ?> หมายถึงผู้ใช้ยอมรับเงื่อนไข ข้อกำหนด และแนวทางการใช้งานต่อไปนี้ โดยระบบนี้จัดทำขึ้นเพื่อเป็นเครื่องมือช่วยเหลือในการตอบคำถามและอำนวยความสะดวกแก่ผู้ใช้เท่านั้น ผู้ใช้ควรใช้ดุลยพินิจของตนเองประกอบการใช้งาน และตรวจสอบความถูกต้องของข้อมูลก่อนนำไปใช้จริงทุกครั้ง
                            </p>

                            <ul>
                                <li>
                                    <strong>AI ไม่ใช่ครูหรือหนังสือเรียน:</strong>
                                    คำตอบที่ AI สร้างขึ้นอาจมีข้อผิดพลาด ไม่อัปเดต หรือบางครั้งอาจ "แต่งข้อมูลขึ้นมาเอง" ผู้ใช้ต้องใช้วิจารณญาณ และตรวจสอบความถูกต้องจากหนังสือคู่มือหรือแหล่งข้อมูลที่เชื่อถือได้ก่อนนำไปใช้เสมอ
                                </li>
                                <li>
                                    <strong>ใช้เป็นผู้ช่วย ไม่ใช่คนทำการบ้านให้:</strong>
                                    สนับสนุนให้ใช้ AI เพื่อหาไอเดีย สรุปเนื้อหา หรือติวหนังสือ แต่ <strong>ห้าม</strong> คัดลอกคำตอบของ AI ไปส่งครูโดยตรงเพื่ออ้างว่าเป็นผลงานของตนเอง (Plagiarism) ผู้ให้บริการไม่รับผิดชอบต่อผลการเรียนหรือคะแนนใดๆ ที่เกิดจากการใช้ AI ผิดวิธี
                                </li>
                                <li>
                                    <strong>ระวังข้อมูลส่วนตัว (Privacy):</strong>
                                    ห้ามพิมพ์ข้อมูลส่วนตัวที่สำคัญลงในช่องแชท เช่น รหัสประจำตัวนักเรียน รหัสผ่าน เบอร์โทรศัพท์ ที่อยู่ หรือเรื่องส่วนตัวของเพื่อนและครอบครัวโดยเด็ดขาด
                                </li>
                                <li>
                                    <strong>RW-AI คือผู้ช่วย ไม่ใช่ข้อเท็จจริงทั้งหมด:</strong>
                                    บริการนี้เป็นเพียงการประมวลผลข้อมูลชุดหนึ่งเพื่อเป็นแนวทางในการศึกษาเท่านั้น เราไม่แนะนำให้นำข้อมูลไปใช้เป็นข้ออ้างอิงหลักในการตัดสินหรือถกเถียงกับผู้อื่น เพราะ AI มีข้อจำกัดด้านความถูกต้องแม่นยำ 100% การนำข้อมูลไปใช้โดยไม่ตรวจสอบก่อนถือเป็นความรับผิดชอบของผู้ใช้งานแต่เพียงผู้เดียว
                                </li>
                                <li>
                                    <strong>ใช้งานอย่างสร้างสรรค์และให้เกียรติผู้อื่น:</strong>
                                    ห้ามใช้ระบบนี้เพื่อสร้างเนื้อหาที่ใช้กลั่นแกล้งผู้อื่น (Cyberbullying) คำหยาบคาย ลามกอนาจาร หรือเนื้อหาที่ผิดกฎระเบียบของโรงเรียนและผิดกฎหมาย
                                </li>
                                <li>
                                    <strong>ผู้ใช้งานต้องรับผิดชอบการกระทำของตนเอง:</strong>
                                    ผู้ให้บริการระบบ AI จัดทำขึ้นเพื่อให้ความรู้และอำนวยความสะดวก จะไม่รับผิดชอบต่อความเสียหายใดๆ ที่เกิดขึ้นจากการนำข้อมูลไปใช้ในทางที่ผิด หรือเกิดจากความเข้าใจผิดของผู้ใช้งานเอง
                                </li>
                                <li>
                                    <strong>ข้อมูลการสนทนา:</strong>
                                    ข้อความที่พูดคุยกับ AI จะถูกส่งไปประมวลผลเพื่อหาคำตอบเท่านั้น และจะไม่มีการจัดเก็บประวัติการสนทนาในฐานข้อมูลถาวร ผู้ให้บริการขอสงวนสิทธิ์ในการระงับการใช้งานของผู้ที่ละเมิดกฎกติกาเหล่านี้
                                </li>
                            </ul>

                            <p>
                                หากผู้ใช้งานไม่ยอมรับข้อตกลงเหล่านี้ กรุณาหยุดใช้งานระบบทันที และสามารถติดต่อผู้ดูแลระบบเพื่อสอบถามรายละเอียดเพิ่มเติมได้
                            </p>
                        </div>
                    </section>
                </div>

                <div class="mt-6 bg-blue-50 border border-blue-100 rounded-2xl p-4 sm:p-5">
                    <p class="text-sm sm:text-base text-slate-700 leading-relaxed">
                        ข้อความและคำตอบที่ได้จากระบบนี้เป็นเพียงข้อมูลประกอบการใช้งานเท่านั้น ไม่ถือเป็นคำยืนยัน ความเห็นทางวิชาการ หรือคำแนะนำอย่างเป็นทางการ ผู้ใช้ควรตรวจสอบกับแหล่งข้อมูลที่เชื่อถือได้ก่อนตัดสินใจนำไปใช้ โดยเฉพาะข้อมูลที่เกี่ยวข้องกับการเรียน การเงิน กฎหมาย หรือเรื่องสำคัญอื่นๆ หากผู้ใช้เลือกที่จะใช้งานต่อ ถือว่าผู้ใช้ยอมรับว่าตนเองเป็นผู้รับผิดชอบต่อการใช้ข้อมูลและผลลัพธ์ที่เกิดขึ้นจากการใช้งานนั้นทั้งหมด
                    </p>
                </div>

                <footer class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs sm:text-sm text-slate-500">
                    <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. All rights reserved.</p>
                   
                </footer>
            </div>
        </div>
    </div>

    <script>
        const tabs = document.querySelectorAll('.policy-tab');
        const panels = document.querySelectorAll('.policy-panel');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.tab;

                tabs.forEach(t => {
                    t.classList.remove('tab-active');
                    t.classList.add('bg-slate-100', 'hover:bg-slate-200', 'text-slate-700');
                });

                tab.classList.add('tab-active');
                tab.classList.remove('bg-slate-100', 'hover:bg-slate-200', 'text-slate-700');

                panels.forEach(panel => {
                    panel.classList.toggle('hidden', panel.id !== target);
                });
            });
        });
    </script>
</body>
</html>

<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Thai language strings for mod_livestream.
 *
 * @package    mod_livestream
 * @copyright  2026 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['attendance'] = 'การเช็กชื่อ';
$string['attendeecount'] = 'จำนวนนักเรียนที่พบ';
$string['attendeeduration'] = 'ระยะเวลา';
$string['attendeefirstseen'] = 'เข้าเมื่อ';
$string['attendeelastseen'] = 'ออกเมื่อ';
$string['attendeeusername'] = 'ชื่อผู้ใช้';
$string['calendarstart'] = '{$a} เริ่มถ่ายทอดสด';
$string['chatheading'] = 'แชทสด';
$string['chatplaceholder'] = 'พิมพ์ข้อความ…';
$string['chatsend'] = 'ส่ง';
$string['coursenavlive'] = 'กำลังถ่ายทอดสด';
$string['coursenavstreams'] = 'ถ่ายทอดสด';
$string['downloadcsv'] = 'ดาวน์โหลด CSV';
$string['errorobsnotconfigured'] = 'ยังไม่ได้ตั้งค่าการสตรีมด้วย OBS ผู้ดูแลระบบต้องตั้งค่า RTMP server และ HLS base URL ในการตั้งค่าปลั๊กอินก่อน';
$string['errorzoomapi'] = 'ข้อผิดพลาดจาก Zoom API: {$a}';
$string['errorzoomnotconfigured'] = 'คุณยังไม่ได้เชื่อมต่อบัญชี Zoom ของตัวเอง ไปที่ "จัดการบัญชี Zoom ของฉัน" (ในเมนูนำทางของรายวิชา) เพื่อกรอกข้อมูล Zoom Server-to-Server OAuth ก่อน';
$string['joinmeeting'] = 'เข้าร่วมประชุม Zoom';
$string['managezoomaccount'] = 'จัดการบัญชี Zoom ของฉัน';
$string['livestream:addinstance'] = 'เพิ่มกิจกรรมถ่ายทอดสดใหม่';
$string['livestream:chat'] = 'ร่วมแชทสด';
$string['livestream:managestream'] = 'จัดการสตรีม (ดู stream key และปุ่มควบคุมของโฮสต์)';
$string['livestream:view'] = 'ดูกิจกรรมถ่ายทอดสด';
$string['livestreamname'] = 'ชื่อการถ่ายทอดสด';
$string['meetingid'] = 'Meeting ID';
$string['modulename'] = 'ถ่ายทอดสด';
$string['modulename_help'] = 'กิจกรรมถ่ายทอดสดช่วยให้ครูสอนสดถึงนักเรียนได้ 2 แบบ คือสตรีมจากโปรแกรม OBS Studio ไปยัง media server ของสถาบัน (นักเรียนดูผ่านเครื่องเล่นวิดีโอในหน้านี้) หรือผ่านห้องประชุม Zoom ที่ระบบสร้างให้อัตโนมัติ';
$string['modulenameplural'] = 'ถ่ายทอดสด';
$string['noattendees'] = 'ไม่พบนักเรียนที่ดูการถ่ายทอดสดนี้';
$string['nolivestreams'] = 'รายวิชานี้ยังไม่มีการถ่ายทอดสด';
$string['nosessions'] = 'ยังไม่มีประวัติการถ่ายทอดสด';
$string['obssetup'] = 'ตั้งค่า OBS (สำหรับครูเท่านั้น)';
$string['obssetup_help'] = 'ในโปรแกรม OBS Studio ไปที่ Settings → Stream เลือก "Custom..." แล้วคัดลอก Server URL และ Stream key ด้านล่างไปใส่ เมื่อพร้อมสอนให้กด "Start Streaming" ใน OBS — เครื่องเล่นในหน้านี้จะเริ่มเล่นอัตโนมัติภายในไม่กี่วินาที';
$string['pluginadministration'] = 'การจัดการถ่ายทอดสด';
$string['pluginname'] = 'ถ่ายทอดสด';
$string['privacy:metadata:livestream_attendance'] = 'บันทึกเวลาที่นักเรียนเข้าดูการถ่ายทอดสด ใช้สำหรับการเช็กชื่อ';
$string['privacy:metadata:livestream_attendance:firstseen'] = 'เวลาที่พบผู้ใช้เริ่มดูการถ่ายทอดสดนี้';
$string['privacy:metadata:livestream_attendance:lastseen'] = 'เวลาล่าสุดที่พบผู้ใช้ดูการถ่ายทอดสดนี้';
$string['privacy:metadata:livestream_attendance:userid'] = 'รหัสผู้ใช้ที่เข้าดู';
$string['privacy:metadata:livestream_zoom_account'] = 'ข้อมูลแอป Zoom Server-to-Server OAuth ส่วนตัวของคุณ ใช้สร้างห้องประชุม Zoom ภายใต้บัญชี Zoom ของคุณเอง';
$string['privacy:metadata:livestream_zoom_account:accountid'] = 'Account ID ของแอป Zoom Server-to-Server OAuth ของคุณ';
$string['privacy:metadata:livestream_zoom_account:clientid'] = 'Client ID ของแอป Zoom Server-to-Server OAuth ของคุณ';
$string['privacy:metadata:livestream_zoom_account:clientsecret'] = 'Client secret ของแอป Zoom Server-to-Server OAuth ของคุณ';
$string['privacy:metadata:zoom'] = 'เมื่อใช้ห้องประชุม Zoom ผู้เข้าร่วมจะเข้าผ่านระบบของ Zoom ซึ่งได้รับข้อมูลตามนโยบายความเป็นส่วนตัวของ Zoom เอง';
$string['privacy:metadata:zoom:email'] = 'อีเมลที่ผู้เข้าร่วมใช้ใน Zoom';
$string['privacy:metadata:zoom:fullname'] = 'ชื่อที่แสดงของผู้เข้าร่วมใน Zoom';
$string['recordingurl'] = 'ลิงก์วิดีโอย้อนหลัง';
$string['recordingurl_help'] = 'ไม่บังคับ หลังสอนเสร็จสามารถวางลิงก์วิดีโอบันทึกการสอน (เช่นวิดีโอในระบบหรือ YouTube) แล้วนักเรียนจะเห็นปุ่ม "ดูย้อนหลัง"';
$string['rtmpserver'] = 'Server (RTMP)';
$string['scheduledfor'] = 'กำหนดเริ่ม {$a}';
$string['sessionend'] = 'เวลาจบ';
$string['sessioninprogress'] = 'กำลังถ่ายทอดสด';
$string['sessionrange'] = '{$a->start} – {$a->end}';
$string['sessionstart'] = 'เวลาเริ่ม';
$string['settinghlsbaseurl'] = 'HLS base URL';
$string['settinghlsbaseurl_desc'] = 'URL สาธารณะของ media server ที่ให้บริการ HLS เช่น https://media.example.com:8888 — เครื่องเล่นจะเรียก {baseurl}/{streamkey}/index.m3u8 ต้องเข้าถึงได้จากเบราว์เซอร์ของนักเรียน (แนะนำ HTTPS)';
$string['settinghlsjsurl'] = 'hls.js URL';
$string['settinghlsjsurl_desc'] = 'URL ของไลบรารี hls.js ที่เครื่องเล่นใช้ ค่าเริ่มต้นโหลดจาก CDN jsDelivr หรือจะชี้ไปยังไฟล์ที่โฮสต์เองก็ได้';
$string['settingplaybackbaseurl'] = 'URL สำหรับดูย้อนหลัง';
$string['settingplaybackbaseurl_desc'] = 'Base URL ของบริการเล่นวิดีโอย้อนหลังบน media server เช่น https://media.example.com:9996 เมื่อตั้งค่าไว้ ปุ่ม "ดูย้อนหลัง" จะปรากฏอัตโนมัติหลังสตรีมจบ หากเว้นว่างคลิปจะถูกเก็บไว้บนดิสก์เฉยๆ ต้องเข้าถึงได้จากเบราว์เซอร์ของนักเรียน (แนะนำ HTTPS)';
$string['settingrtmpserver'] = 'RTMP server URL';
$string['settingrtmpserver_desc'] = 'URL สำหรับให้ครูใส่ใน OBS เช่น rtmp://media.example.com:1935 (ห้ามมี path ต่อท้ายเช่น /live และไม่ต้องใส่ stream key — ตัว stream key คือ path บน media server อยู่แล้ว)';
$string['settingsobsheading'] = 'OBS / media server';
$string['settingsobsheading_desc'] = 'การตั้งค่า media server ที่รับ RTMP จาก OBS และส่ง HLS ให้นักเรียน ดูโฟลเดอร์ media-server ในชุดปลั๊กอินสำหรับ docker-compose ของ MediaMTX ที่พร้อมใช้';
$string['settingszoomheading'] = 'Zoom';
$string['settingszoomheading_desc'] = 'Zoom ไม่มีการตั้งค่าระดับเว็บไซต์ เพราะครูแต่ละคนอาจมีบัญชี/องค์กร Zoom แยกกัน จึงให้ครูแต่ละคนกรอกข้อมูลของตัวเองผ่านลิงก์ "จัดการบัญชี Zoom ของฉัน" ในเมนูนำทางของรายวิชา (mod/livestream/zoomaccount.php) แทนที่นี่';
$string['settingzoomaccountid'] = 'Zoom account ID';
$string['settingzoomclientid'] = 'Zoom client ID';
$string['settingzoomclientsecret'] = 'Zoom client secret';
$string['startmeeting'] = 'เริ่มการประชุม (โฮสต์)';
$string['starttime'] = 'กำหนดเวลาเริ่ม';
$string['starttime_help'] = 'ไม่บังคับ หากตั้งไว้ การถ่ายทอดสดจะปรากฏในปฏิทินรายวิชา และนักเรียนจะเห็นเวลาเริ่มในหน้ากิจกรรม';
$string['statuschecking'] = 'กำลังตรวจสอบสตรีม…';
$string['statuslive'] = 'สด';
$string['statusoffline'] = 'ออฟไลน์';
$string['streamduration'] = 'ระยะเวลาโดยประมาณ';
$string['streamkey'] = 'Stream key';
$string['streamkeywarning'] = 'เก็บ stream key ไว้เป็นความลับ — ใครก็ตามที่มี key นี้สามารถถ่ายทอดสดในกิจกรรมนี้ได้';
$string['streamoffline'] = 'ยังไม่เริ่มถ่ายทอดสด หน้านี้จะเริ่มเล่นอัตโนมัติทันทีที่ครูเริ่มสตรีม';
$string['streamsettings'] = 'การตั้งค่าสตรีม';
$string['streamtype'] = 'ประเภทการถ่ายทอดสด';
$string['streamtype_help'] = '* **OBS / media server** — สตรีมจากโปรแกรม OBS Studio ไปยัง media server ของสถาบัน นักเรียนดูผ่านเครื่องเล่นในหน้านี้
* **ห้องประชุม Zoom** — ระบบสร้างห้องประชุม Zoom ให้อัตโนมัติ หากตั้งค่า media server ไว้ด้วย Zoom จะส่งภาพเข้าเครื่องเล่นที่ฝังในหน้านี้ (นักเรียนอยู่ในหน้านี้ได้เลย) ถ้าไม่ได้ตั้งค่า นักเรียนจะเข้าร่วมผ่าน Zoom';
$string['task_close_stale_sessions'] = 'ปิดรอบการถ่ายทอดสดที่ค้างอยู่';
$string['typeobs'] = 'OBS / media server';
$string['typezoom'] = 'ห้องประชุม Zoom';
$string['watchrecording'] = 'ดูย้อนหลัง';
$string['zoomaccountintro'] = 'ห้องประชุมสำหรับกิจกรรมถ่ายทอดสดแบบ Zoom ของคุณจะถูกสร้างภายใต้บัญชี Zoom ของคุณเอง สร้างแอป Server-to-Server OAuth ได้ที่ marketplace.zoom.us (Develop → Build App → Server-to-Server OAuth) โดยต้องมี scope meeting:write แล้วนำข้อมูลมากรอกด้านล่าง';
$string['zoomaccountremove'] = 'ลบบัญชี Zoom ของฉัน';
$string['zoomaccountremoveconfirm'] = 'ต้องการลบข้อมูลบัญชี Zoom ของคุณหรือไม่? ห้องประชุม Zoom ที่สร้างไว้แล้วจะยังใช้งานได้ตามปกติ แต่คุณจะไม่สามารถสร้างหรือจัดการห้องใหม่ได้จนกว่าจะเชื่อมต่อบัญชีอีกครั้ง';
$string['zoomaccountremoved'] = 'ลบบัญชี Zoom ของคุณแล้ว';
$string['zoomaccountsave'] = 'บันทึกบัญชี Zoom';
$string['zoomaccountsaved'] = 'บันทึกบัญชี Zoom ของคุณแล้ว';
$string['zoomaccounttitle'] = 'บัญชี Zoom ของฉัน';
$string['zoomcreatefailed'] = 'บันทึกกิจกรรมแล้ว แต่สร้างห้องประชุม Zoom ไม่สำเร็จ: {$a} — แก้ไขและบันทึกกิจกรรมอีกครั้งเพื่อลองใหม่';
$string['zoomembedhelp'] = 'นักเรียนดูการสอนนี้ผ่านเครื่องเล่นที่ฝังอยู่ด้านล่าง โดยไม่ต้องเปิด Zoom เอง เริ่มการประชุมแล้วในโปรแกรม Zoom เลือก <strong>More → Live on Custom Live Streaming Service</strong> เพื่อออกอากาศ เครื่องเล่นจะเริ่มเล่นเองภายในไม่กี่วินาที';
$string['zoomlivestreamfailed'] = 'บันทึกกิจกรรมแล้ว แต่ตั้งค่า custom live stream ของ Zoom ไม่สำเร็จ: {$a} — ตรวจสอบว่าบัญชี Zoom เปิด "Custom Live Streaming Service" และแอป OAuth มี scope สำหรับ live streaming แล้ว นักเรียนยังเข้าห้องประชุมโดยตรงได้';
$string['zoommeetingmissing'] = 'กิจกรรมนี้ยังไม่มีห้องประชุม Zoom ครูต้องแก้ไขและบันทึกกิจกรรมเพื่อสร้างห้องประชุม';
$string['zoompasscode'] = 'รหัสผ่านห้องประชุม';
$string['zoompasscode_help'] = 'ไม่บังคับ หากเว้นว่าง Zoom อาจสร้างรหัสให้ตามการตั้งค่าความปลอดภัยของบัญชี';
$string['zoomteacherheading'] = 'ปุ่มควบคุมโฮสต์ Zoom (สำหรับครูเท่านั้น)';
$string['zoomupdatefailed'] = 'บันทึกกิจกรรมแล้ว แต่อัปเดตห้องประชุม Zoom ไม่สำเร็จ: {$a}';

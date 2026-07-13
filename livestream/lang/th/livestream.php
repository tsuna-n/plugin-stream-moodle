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

$string['calendarstart'] = '{$a} เริ่มถ่ายทอดสด';
$string['coursenavstreams'] = 'ถ่ายทอดสด';
$string['errorobsnotconfigured'] = 'ยังไม่ได้ตั้งค่าการสตรีมด้วย OBS ผู้ดูแลระบบต้องตั้งค่า RTMP server และ HLS base URL ในการตั้งค่าปลั๊กอินก่อน';
$string['errorzoomapi'] = 'ข้อผิดพลาดจาก Zoom API: {$a}';
$string['errorzoomnotconfigured'] = 'ยังไม่ได้ตั้งค่า Zoom ผู้ดูแลระบบต้องกรอกข้อมูล Zoom Server-to-Server OAuth ในการตั้งค่าปลั๊กอินก่อน';
$string['joinmeeting'] = 'เข้าร่วมประชุม Zoom';
$string['livestream:addinstance'] = 'เพิ่มกิจกรรมถ่ายทอดสดใหม่';
$string['livestream:managestream'] = 'จัดการสตรีม (ดู stream key และปุ่มควบคุมของโฮสต์)';
$string['livestream:view'] = 'ดูกิจกรรมถ่ายทอดสด';
$string['livestreamname'] = 'ชื่อการถ่ายทอดสด';
$string['meetingid'] = 'Meeting ID';
$string['modulename'] = 'ถ่ายทอดสด';
$string['modulename_help'] = 'กิจกรรมถ่ายทอดสดช่วยให้ครูสอนสดถึงนักเรียนได้ 2 แบบ คือสตรีมจากโปรแกรม OBS Studio ไปยัง media server ของสถาบัน (นักเรียนดูผ่านเครื่องเล่นวิดีโอในหน้านี้) หรือผ่านห้องประชุม Zoom ที่ระบบสร้างให้อัตโนมัติ';
$string['modulenameplural'] = 'ถ่ายทอดสด';
$string['nolivestreams'] = 'รายวิชานี้ยังไม่มีการถ่ายทอดสด';
$string['obssetup'] = 'ตั้งค่า OBS (สำหรับครูเท่านั้น)';
$string['obssetup_help'] = 'ในโปรแกรม OBS Studio ไปที่ Settings → Stream เลือก "Custom..." แล้วคัดลอก Server URL และ Stream key ด้านล่างไปใส่ เมื่อพร้อมสอนให้กด "Start Streaming" ใน OBS — เครื่องเล่นในหน้านี้จะเริ่มเล่นอัตโนมัติภายในไม่กี่วินาที';
$string['pluginadministration'] = 'การจัดการถ่ายทอดสด';
$string['pluginname'] = 'ถ่ายทอดสด';
$string['privacy:metadata:zoom'] = 'เมื่อใช้ห้องประชุม Zoom ผู้เข้าร่วมจะเข้าผ่านระบบของ Zoom ซึ่งได้รับข้อมูลตามนโยบายความเป็นส่วนตัวของ Zoom เอง';
$string['privacy:metadata:zoom:email'] = 'อีเมลที่ผู้เข้าร่วมใช้ใน Zoom';
$string['privacy:metadata:zoom:fullname'] = 'ชื่อที่แสดงของผู้เข้าร่วมใน Zoom';
$string['recordingurl'] = 'ลิงก์วิดีโอย้อนหลัง';
$string['recordingurl_help'] = 'ไม่บังคับ หลังสอนเสร็จสามารถวางลิงก์วิดีโอบันทึกการสอน (เช่นวิดีโอในระบบหรือ YouTube) แล้วนักเรียนจะเห็นปุ่ม "ดูย้อนหลัง"';
$string['rtmpserver'] = 'Server (RTMP)';
$string['scheduledfor'] = 'กำหนดเริ่ม {$a}';
$string['settinghlsbaseurl'] = 'HLS base URL';
$string['settinghlsbaseurl_desc'] = 'URL สาธารณะของ media server ที่ให้บริการ HLS เช่น https://media.example.com:8888 — เครื่องเล่นจะเรียก {baseurl}/{streamkey}/index.m3u8 ต้องเข้าถึงได้จากเบราว์เซอร์ของนักเรียน (แนะนำ HTTPS)';
$string['settinghlsjsurl'] = 'hls.js URL';
$string['settinghlsjsurl_desc'] = 'URL ของไลบรารี hls.js ที่เครื่องเล่นใช้ ค่าเริ่มต้นโหลดจาก CDN jsDelivr หรือจะชี้ไปยังไฟล์ที่โฮสต์เองก็ได้';
$string['settingrtmpserver'] = 'RTMP server URL';
$string['settingrtmpserver_desc'] = 'URL สำหรับให้ครูใส่ใน OBS เช่น rtmp://media.example.com:1935 (ห้ามมี path ต่อท้ายเช่น /live และไม่ต้องใส่ stream key — ตัว stream key คือ path บน media server อยู่แล้ว)';
$string['settingsobsheading'] = 'OBS / media server';
$string['settingsobsheading_desc'] = 'การตั้งค่า media server ที่รับ RTMP จาก OBS และส่ง HLS ให้นักเรียน ดูโฟลเดอร์ media-server ในชุดปลั๊กอินสำหรับ docker-compose ของ MediaMTX ที่พร้อมใช้';
$string['settingszoomheading'] = 'Zoom';
$string['settingszoomheading_desc'] = 'ข้อมูลแอป Server-to-Server OAuth สร้างแอปได้ที่ marketplace.zoom.us (Develop → Build App → Server-to-Server OAuth) และต้องมี scope meeting:write';
$string['settingzoomaccountid'] = 'Zoom account ID';
$string['settingzoomaccountid_desc'] = 'Account ID ของแอป Zoom Server-to-Server OAuth';
$string['settingzoomclientid'] = 'Zoom client ID';
$string['settingzoomclientid_desc'] = 'Client ID ของแอป Zoom Server-to-Server OAuth';
$string['settingzoomclientsecret'] = 'Zoom client secret';
$string['settingzoomclientsecret_desc'] = 'Client secret ของแอป Zoom Server-to-Server OAuth';
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
* **ห้องประชุม Zoom** — ระบบสร้างห้องประชุม Zoom ให้อัตโนมัติ นักเรียนเข้าร่วมผ่าน Zoom';
$string['typeobs'] = 'OBS / media server';
$string['typezoom'] = 'ห้องประชุม Zoom';
$string['watchrecording'] = 'ดูย้อนหลัง';
$string['zoomcreatefailed'] = 'บันทึกกิจกรรมแล้ว แต่สร้างห้องประชุม Zoom ไม่สำเร็จ: {$a} — แก้ไขและบันทึกกิจกรรมอีกครั้งเพื่อลองใหม่';
$string['zoommeetingmissing'] = 'กิจกรรมนี้ยังไม่มีห้องประชุม Zoom ครูต้องแก้ไขและบันทึกกิจกรรมเพื่อสร้างห้องประชุม';
$string['zoompasscode'] = 'รหัสผ่านห้องประชุม';
$string['zoompasscode_help'] = 'ไม่บังคับ หากเว้นว่าง Zoom อาจสร้างรหัสให้ตามการตั้งค่าความปลอดภัยของบัญชี';
$string['zoomupdatefailed'] = 'บันทึกกิจกรรมแล้ว แต่อัปเดตห้องประชุม Zoom ไม่สำเร็จ: {$a}';

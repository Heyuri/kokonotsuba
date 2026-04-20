<?php
/*
Pixmicat! dil dosyası - Turkish (TR) [tr_TR]
*/

namespace Kokonotsuba\lang;

if (!isset($language)) $language = Array();

// pixmicat.php
$language['board_not_found']            = 'Tahta bulunamadı!';
$language['no_boards_found']            = 'Tahtalar bulunamadı!';
$language['error_board_api']            = 'Tahta API\'si oluşturulurken hata meydana geldi';
$language['error_invalid_board_id']     = 'Geçersiz tahta UID\'i!';
$language['error_invalid_board_endpoint'] = 'Geçersiz tahta API endpoint sayfası';
$language['error_invalid_thread_id']    = 'Sisteme geçersiz thread ID\'si verilmiş';
$language['page_not_found']				= 'Üzgünüz, ulaşmaya çalıştığınız sayfa bulunamadı.';
$language['thread_not_found']			= 'Thread bulunamadı!';
$language['thread_deleted']			    = 'Bu thread silinmiş!';
$language['del_head']					= 'Postu Sil: ';
$language['del_img_only']				= 'Sadece Dosyayı Sil';
$language['del_pass']					= 'Şifre: ';
$language['del_btn']					= 'Sil';
$language['prev_page']					= 'Geriye Dön';
$language['first_page']					= 'İlk';
$language['all_pages']					= 'Hepsi';
$language['next_page']					= 'Sonraki';
$language['last_page']					= 'Son';
$language['img_sample']					= 'Önizleme';
$language['img_filename']				= 'Dosya: ';
$language['reply_btn']					= 'Yanıtla';
$language['recent_btn']                 = 'Son %s post';
$language['warn_sizelimit']				= 'Resim, depolama sınırı yüzünden yakında silinecek.';
$language['warn_oldthread']				= 'Thread eski olduğu için yakında silinecek.';
$language['warn_locked']				= 'Thread admin tarafından kilitlendi.';
$language['notice_omitted']				= '%1$s post dahil edilmedi. Görmek için yanıt butonuna basınız.';
$language['notice_viewing_page']  = 'Sayfa %1$s / %2$s görüntüleniyor';
$language['notice_viewing_last_posts']	= 'Son %1$s %2$s görüntüleniyor';
$language['post_name']				  	= 'İsim: ';
$language['post_category']				= 'Kategori: ';
$language['regist_notpost']				= 'Post atabilmek için lütfen board üzerindeki formu kullanın.';
$language['regist_nospam']				= 'Spambot karşıt sistemi aktive edildi.';
$language['regist_ipfiltered']			= 'Bağlantınız reddedildi. Sebep: %1$s';
$language['regist_wordfiltered']		= 'Yasaklı kelimeler tespit edildi, post atılamadı.';
$language['regist_upload_exceedphp']	= 'Yükleme başarısız.<br>Dosya boyutu sunucu limitini aşıyor.';
$language['regist_upload_exceedcustom']	= 'Yükleme başarısız.<br>Dosya boyutu limiti aşıyor.';
$language['regist_upload_incompelete']	= 'Yükleme başarısız.<br>Yükleme tamamlanamadı. Lütfen yeniden deneyin.';
$language['regist_upload_direrror']		= 'Yükleme başarısız.<br>Yanlış dosya yükleme dizin ayarı. Lütfen sistem adminine haber verin.';
$language['regist_upload_noimg']		= 'Yeni threadler için bir resim gerekli!';
$language['regist_upload_filenotfound']	= 'Yükleme başarısız.<br>Sunucu dosya yüklemeye izin vermiyor, erişim reddedildi, veya desteklenmeyen dosya türü.';
$language['regist_upload_killincomp']	= 'DİKKAT: Yüklemeniz yanlış dosya boyutundan ötürü iptal edildi.';
$language['regist_upload_notimage']		= 'Yükleme başarısız.<br>Resim dışında dosya türleri desteklenmiyor.';
$language['regist_upload_notsupport']	= 'Desteklenmeyen resim.';
$language['regist_upload_blocked']		= 'Yükleme başarısız.<br>Bu resmin yüklenmesi yasaklanmış.';
$language['regist_uploaded']			= '%1$s Resmi Yüklendi.<br>';
$language['regist_sakuradetected']		= 'Big5 sakura Japonca karakterleri tespit edildi.';
$language['regist_withoutname']			= 'Lütfen isminizi girin.';
$language['regist_withoutcomment']		= 'Eğer bir resim yüklemiyorsanız lütfen yanıt alanını doldurun.';
$language['regist_withoutimage']		= 'Lütfen bir resim seçin.';
$language['regist_nametoolong']			= 'İsim çok uzun.';
$language['regist_emailtoolong']		= 'E-mail çok uzun.';
$language['regist_topictoolong']		= 'Konu çok uzun.';
$language['regist_passtoolong']		    = 'Şifre çok uzun.';
$language['regist_longthreadnum']		= 'Yanıt verdiğiniz thread yanlış olabilir.';
$language['admin']						= 'Admin';
$language['deletor']					= 'Mod';
$language['trip_pre']					= '!';
$language['trip_pre_fake']				= '|';
$language['cap_char']					= '¤';
$language['cap_char_fake']				= 'ø';
$language['regist_commenttoolong']		= 'Yorum çok uzun.';
$language['notice_incompletefile']		= 'DİKKAT: Resim Kusurlu.';
$language['sun']						= 'Paz';
$language['mon']						= 'Pzt';
$language['tue']						= 'Sal';
$language['wed']						= 'Çar';
$language['thu']						= 'Per';
$language['fri']						= 'Cum';
$language['sat']						= 'Cmt';
$language['regist_successivepost']		= 'Kesintisiz postlamak için lütfen biraz bekleyin.';
$language['regist_duplicatefile']		= 'Yükleme başarısız.<br>Aynı dosya yakın geçmişte yüklenmiş.';
$language['regist_threaddeleted']		= 'Bu thread silinmiş!';
$language['regist_threadlocked']		= 'Thread admin tarafından kilitlenmiş!';
$language['regist_redirect']			= '%1$s Yönlendiriliyor... <p>Eğer tarayıcınız otomatik olarak yönlendirmiyorsa tıklayın: <a href="%2$s">Geri dön</a></p>';
$language['del_notchecked']				= 'Silinmek üzere bir şey seçilmedi. Lütfen geri dönüp seçin.';
$language['del_wrongpwornotfound']		= 'Öyle bir post yok veya şifre hatalı.';
$language['admin_wrongpassword']		= 'Şifre hatalı';
$language['return']						= 'Geri dön';
$language['admin_remake']				= 'Yeniden kur';
$language['admin_frontendmanage']		= 'Ara yüzden modere et (Oturum açılması zorunlu)';
$language['admin_delete']				= 'Sil';
$language['admin_top']					= 'Administratör modu';
$language['admin_logged_in_as'] = '%1$s (%2$s) olarak giriş yapıldı';
$language['admin_logout']       = 'Çıkış yap';
$language['admin_manageposts']	= 'Postları yönet';
$language['admin_optimize']			= 'Optimize et';
$language['admin_check']				= 'Veri kaynağını kontrol et';
$language['admin_repair']				= 'Veri kaynağını tamir et';
$language['admin_export']				= 'Veriyi dışarı aktar';
$language['admin_verify_btn']			= 'Giriş yap';
$language['admin_archive']				= '<th>Arşiv</th>';
$language['admin_notices']				= '<ul><li>Eğer bir post silecekseniz, postun önündeki "sil" kutucuğunu seçip Submit butonuna basın.</li><li>If you want to delete image only, please check "Delete image only" checkbox and follow normal deletion procedures.</li><li>If you want to lock/unlock a thread, please check "Stop" checkbox of that thread and click "Submit" button.</li><li>Please press "Update" after managing posts.</li></ul>';
$language['admin_submit_btn']			= 'Submit';
$language['admin_reset_btn']			= 'Reset';
$language['admin_list_header']			= '<tr><th class="colFunc">Func</th><th class="colDel">Del</th><th class="colBoard">Board</th><th class="colDate">Date</th><th class="colSub">Subject</th><th class="colName">Name</th><th class="colComment">Comment</th><th class="colHost">Host</th><th class="colImage"><div>Image (Bytes)</div><div>MD5 checksum</div></th></tr>';
$language['admin_archive_btn']			= 'A';
$language['admin_stop_btn']				= 'Stop';
$language['admin_totalsize']			= '[Total size of images: <b>%1$s</b> KB ]';
$language['search_disabled']			= 'Search is disabled!';
$language['search_top']					= 'Arama';
$language['search_notice']				= '<li>Lütfen anahtar kelimeleri girip aramanın hedefini seçin, ve <q>Ara</q> butonuna basın.</li><li>Aralarında boşluk bırakarak birden fazla anahtar kelimeyi arayabilirsiniz.</li><li>Arama metodunu (<q>VE araması</q> ve <q>YA DA araması</q>) yaparak farklı şekillerde birden fazla anahtar kelime arayabilirsiniz.</li>';
$language['search_keyword']				= 'Anahtar kelime';
$language['search_target']				= 'Hedef';
$language['search_target_comment']		= 'Yorum';
$language['search_target_name']			= 'İsim';
$language['search_target_topic']		= 'Konu';
$language['search_target_number']		= 'No.';
$language['search_method']				= 'Metod';
$language['search_method_and']			= 'VE';
$language['search_method_or']			= 'YA DA';
$language['search_target_matchword']	= 'Tam kelime eşleşmesi';
$language['search_target_opening_post'] = 'Sadece OPler';
$language['search_submit_btn']			= 'Ara';
$language['search_notfound']			= 'Belirtilen anahatar kelimeler için sonuç bulunamadı.';
$language['search_back']				= 'Geri';
$language['category_nokeyword']			= 'lütfen benzer postlar aramak için kategoriye girin';
$language['category_notfound']			= 'Bu kategori için eşleşen gönderi yok.';
$language['category_recache']			= 'yeniden önbelleğe al';
$language['module_info_top']			= 'Modül bilgisi';
$language['module_loaded']				= 'Modül Yüklendi:';
$language['module_info']				= 'Modül Bilgisi:';
$language['info_top']					= 'Sistem Bilgisi';
$language['info_disabled']				= 'Devre Dışı';
$language['info_enabled']				= 'Etkin';
$language['info_functional']			= 'fonksiyonel';
$language['info_nonfunctional']			= 'fonksiyonel değil';
$language['info_basic']					= 'Basit ayarlar';
$language['info_basic_ver']				= 'Program version';
$language['info_basic_pio']				= 'PIO library backend and version';
$language['info_basic_threadsperpage']	= 'Sayfa başına konu sayısı';
$language['info_basic_threads']			= '';
$language['info_basic_postsperpage']	= 'Replies to show in index';
$language['info_basic_posts']			= '';
$language['info_basic_postsinthread']	= 'Yanıt modunda sayfa başına gönderi sayısı';
$language['info_basic_posts_showall']	= '(Göster:0)';
$language['info_basic_bumpposts']		= 'Do not bump post if reply is more than';
$language['info_basic_bumphours']		= 'Thread bumping hours';
$language['info_basic_hours']			= 'saat(s)';
$language['info_basic_0disable']		= '(Devre Dışı:0)';
$language['info_basic_urllinking']		= 'URL Auto Linking';
$language['info_0no1yes']				= '(Yes:1 No:0)';
$language['info_basic_com_limit']		= 'Maksimum yorum boyutu';
$language['info_basic_com_after']		= ' Bayt';
$language['info_basic_anonpost']		= 'Anonim Paylaşma';
$language['info_basic_anonpost_opt']	= '(Force anonim:2 evet:1 hayır:0)';
$language['info_basic_del_incomplete']	= 'Delete incomplete images';
$language['info_basic_use_sample']		= 'Küçük resimleri kullan (Kalite: %1$s)';
$language['info_0notuse1use']			= '(Use:1 Not used:0)';
$language['info_basic_use_sample_func']	= '+ Thumbnails generation';
$language['info_basic_useblock']		= 'IP engelleme';
$language['info_0disable1enable']		= '(Enable:1 Disable:0)';
$language['info_basic_showid']			= 'ID göster';
$language['info_basic_showid_after']	= '(force show:2 selective show:1 do not show:0)';
$language['info_basic_cr_limit']		= 'Yorum satırı sınırı';
$language['info_basic_cr_after']		= ' Row(s) (unlimited:0)';
$language['info_basic_timezone']		= 'Saat dilimi';
$language['info_basic_threadcount']		= 'Toplam başlık sayısı';
$language['info_basic_theme']			= 'Tema';
$language['info_dsusage_top']			= 'Data source usage';
$language['info_dsusage_max']			= 'Maximum size';
$language['info_dsusage_usage']			= 'Usage';
$language['info_dsusage_count']			= 'Current usage';
$language['info_fileusage_top']			= 'Storage limit of images:';
$language['info_fileusage_limit']		= 'en fazla boy';
$language['info_fileusage_count']		= 'Current usage';
$language['info_fileusage_unlimited']	= 'Unlimited';
$language['info_server_top']			= 'Server information';
$language['info_server_gd']				= 'GD library ';
$language['init_permerror']				= 'No write permission in root directory. Please modify permission settings.<br>';
$language['action_main_notsupport']		= 'Backend does not support this operation.';
$language['action_main_optimize']		= 'Optimization ';
$language['action_main_check']			= 'Check ';
$language['action_main_repair']			= 'Repair ';
$language['action_main_export']			= 'Export ';
$language['action_main_success']		= 'başarılı!';
$language['action_main_failed']			= 'başarısız oldu!';

// lib_common.php
$language['head_home']					= 'Ana Sayfa';
$language['head_catalog']				= 'Katalog';
$language['head_search']				= 'Arama';
$language['head_info']					= 'Bilgi';
$language['head_admin']					= 'Admin';
$language['head_refresh']				= 'Dön';
$language['form_top']					= 'Postlama modu: cevap';
$language['form_showpostform']			= 'Post';
$language['form_hidepostform']			= 'Hide form';
$language['form_name']					= 'isim';
$language['form_email']					= 'E-mail';
$language['form_topic']					= 'Konu';
$language['form_submit_btn']			= 'Gönder';
$language['form_comment']				= 'Yorum';
$language['form_attechment']			= 'Dosya';
$language['form_noattechment']			= 'Dosya yok';
$language['form_contpost']				= 'Continuous';
$language['form_category']				= 'kategori';
$language['form_category_notice']		= ' (ayırmak, için kullanın)';
$language['form_delete_password']		= 'Şifre';
$language['form_delete_password_notice']= ' (silebilmek için)';
$language['form_notice']				= '<li>İzin verilen dosya türleri: %1$s</li><li>En fazla %2$s KB yüklenebilir.</li><li>%3$s * %4$s piksel boyutlarından büyük resimler önizlenecek.</li>';
$language['form_notice_storage_limit']	= '<li>Current storage usage: %1$s KB / %2$s KB</li>';
$language['form_notice_noscript']		= '*** You disabled JavaScript, but this won\'t affect you when browsing and posting.';
$language['error_back']					= 'Dön';
$language['ip_banned']					= 'IP/Ana Bilgisayar Adı Kara Listesinde Listelendi';
$language['ip_dnsbl_banned']			= 'Listed in DNSBL(%1$s) Blacklist';

/* modules */
// onlineCounter
$language['online_counter_text']            = 'Son %3$s %4$s içinde %1$s özgün %2$s (sadece okuyanlar dahil)';
$language['online_counter_user_singular']   = 'kullanıcı';
$language['online_counter_user_multiple']   = 'kullanıcı';
$language['online_counter_minute_singular'] = 'dakika';
$language['online_counter_minute_multiple'] = 'dakika';
// search
$language['no_search_field']                = "Arama yapılacak alan seçilmedi!";
$language['no_search_method']               = "Arama yöntemi seçilmedi!";
// displayIp
$language['posts_itt_display_ip']           = "Bu threaddeki postlar IP adreslerini gösterecektir.";

// mainscript.js // regist_withoutcomment,regist_upload_notsupport,js_convert_sakura
$language['js_convert_sakura']			= 'Big5 sakura Japanese characters detected, please try to convert to standard one.';

$language['attachment_not_found']       = 'Dosya bulunamadı!';
$language['no_attachment_ever']         = 'Bu postta zaten hiçbir dosya yoktu!';
$language['blanket_error'] = "Bir yerlerde bir şeyler yanlış gitti. (;´Д`)";
$language['comment_too_long'] = "Yorum fazla uzun, tümünü görüntülemek için %s postuna bakın.";
$language['post_not_found'] = "Post bulunamadı!";
$language['module_route_not_found'] = "Modül rotası bulunamadı!";
$language['anti_spam_message'] = "Postunuz anti-spam kurallarına takıldı. Bunun bir hata olduğunu düşünüyorsanız yetkililerle iletişime geçin.";
$language['thread_not_found_for_deletion'] = "Silinen post önizlemesi için thread bulunamadı!";
$language['post_singular'] = 'post';
$language['post_multiple'] = 'post';
$language['poster_hash_count'] = 'Bu ID ile %1$s %2$s var.';
$language['score_pre_text'] = 'Skor: %1$s';
$language['view_posts_by_user'] = 'Kullanıcının postlarını görüntüle';
$language['leave_note'] = 'Not bırak';
$language['note_visibility_description'] = 'Bu not sadece diğer yetkililer tarafından görülebilir';
$language['note_title_text'] = 'Bu bir not';
$language['edit_note'] = 'Notu düzenleme';
$language['delete_note'] = 'Notu sil';
$language['note_no_permission'] = 'Bu notu düzenleme yetkiniz yok.';
$language['edit_post'] = 'Postu düzenle';
$language['self_serve_banner_suggest'] = 'Kendi bannerınızı burada göstermek ister misiniz? Başvurmak için tıklayın!';

// DEPRECATED: pm_contacts_section_title, pm_view_thread_page_title, pm_contact_view_no_messages,
// contact_not_selected, pm_no_message, pm_contact_self, pm_compose_title, pm_new_message, pm_no_contacts

$language['private_message_top_bar'] = 'Özel mesajlar';
$language['pm_recipient_and_message_required'] = 'Alıcı ve mesaj gereklidir!';
$language['pm_invalid_recipient'] = 'Geçersiz alıcı tripcode\'u!';
$language['pm_main_title'] = 'Özel mesajlaşma sistemi';
$language['pm_inbox_page_title'] = 'Özel mesaj gelen kutusu';
$language['pm_login_page_title'] = 'Özel mesaj girişi';
$language['pm_login_required'] = 'Özel mesajlaşma sistemini kullanmak için giriş yapmalısınız!';
$language['pm_login_description'] = 'Özel mesajlarınıza erişmek için tripcode\'unuzu girin.';
$language['pm_tripcode_login_hash_note'] = '\'#\' işaretini ve ardından tripcode şifrenizi ekleyin. Güvenli tripcode için iki adet \'#\' (##) kullanın.';
$language['pm_tripcode_login_label'] = 'Tripcode:';
$language['pm_invalid_tripcode'] = 'Geçersiz tripcode girdisi!';
$language['pm_no_conversation'] = 'Bu kullanıcıyla hiçbir konuşma bulunamadı.';
$language['pm_no_messages'] = 'Henüz mesaj yok.';
$language['pm_message_not_found'] = 'Mesaj bulunamadı.';
$language['pm_name_label'] = 'İsim';
$language['pm_subject_label'] = 'Konu';
$language['pm_body_label'] = 'Mesaj';
$language['pm_send_btn'] = 'Gönder';
$language['pm_recipient_label'] = 'Alıcı';
$language['pm_recipient_placeholder'] = '◆tripcode veya ★tripcode';
$language['pm_logged_in_as'] = 'Giriş yapıldı';
$language['pm_logout_btn'] = 'Çıkış yap';
$language['pm_direction_sent'] = 'Kime';
$language['pm_direction_received'] = 'Kimden';
$language['pm_view_label'] = 'Görüntüle';
$language['pm_table_from'] = 'Kimden/Kime';
$language['pm_table_subject'] = 'Konu';
$language['pm_table_preview'] = 'Önizleme';
$language['pm_table_date'] = 'Tarih';
$language['pm_back_to_inbox'] = 'Gelen kutusuna dön';
$language['pm_from_label'] = 'Kimden';
$language['pm_to_label'] = 'Kime';
$language['pm_date_label'] = 'Tarih';
$language['pm_reply_label'] = 'Yanıtla';
$language['pm_user_banned'] = 'Özel mesaj sistemini kullanmanız yasaklandı.';
$language['pm_admin_title'] = 'Özel Mesaj Yönetimi';
$language['admin_nav_pm_title'] = 'Özel mesajları yönet';
$language['admin_nav_pm'] = 'Özel mesajlar';
$language['pm_admin_th_select'] = 'Seç';
$language['pm_admin_th_sender'] = 'Gönderen';
$language['pm_admin_th_recipient'] = 'Alıcı';
$language['pm_admin_th_subject'] = 'Konu';
$language['pm_admin_th_body'] = 'İçerik';
$language['pm_admin_th_ip'] = 'IP Adresi';
$language['pm_admin_th_date'] = 'Tarih';
$language['pm_admin_delete_btn'] = 'Seçilenleri sil';
$language['pm_admin_ban_btn'] = 'Seçili IP\'leri yasakla';
$language['pm_admin_no_messages'] = 'Hiç özel mesaj bulunamadı.';
$language['pm_admin_back'] = 'Mesajlara dön';
$language['pm_notification_title'] = 'Yeni özel mesajlar';
$language['pm_notification_body'] = '%d okunmamış mesajınız var.';

$language['admin_nav_capcodes_title'] = 'Kullanıcı capcode\'larını yönetin ve yetkili capcode\'larını görüntüleyin';
$language['admin_nav_capcodes'] = 'Capcode\'lar';
$language['admin_nav_blotter_title'] = 'Blotter\'ı yönetin';
$language['admin_nav_blotter'] = 'Blotter';
$language['admin_nav_global_message_title'] = 'Genel duyuruyu yönetin';
$language['admin_nav_global_message'] = 'Genel duyuru';
$language['admin_nav_deleted_posts_title'] = 'Silinen postları görüntüleyin';
$language['admin_nav_deleted_posts'] = 'Silinen postlar';
$language['admin_nav_rebuild_title'] = 'Yeniden oluşturma araçları';
$language['admin_nav_rebuild'] = 'Tahtayı yeniden oluştur';
$language['admin_nav_accounts_title'] = 'Kullanıcı hesaplarını ve yetki seviyelerini yönetin';
$language['admin_nav_accounts'] = 'Hesaplar';
$language['admin_nav_ban_title'] = 'Banları ve uyarıları yönetin';
$language['admin_nav_ban'] = 'Banlar';
$language['admin_nav_anti_spam_title'] = 'Anti-spam ayarlarını yönetin';
$language['admin_nav_anti_spam'] = 'Anti-Spam';
$language['admin_nav_posts_title'] = 'Postları yönetin';
$language['admin_nav_posts'] = 'Postlar';
$language['admin_nav_boards_title'] = 'Tahtaları yönetin';
$language['admin_nav_boards'] = 'Tahtalar';
$language['admin_nav_action_log_title'] = 'Yetkili işlem kayıtlarını görüntüleyin';
$language['admin_nav_action_log'] = 'İşlem kaydı';
$language['admin_nav_rebuild_multiple_title'] = 'Toplu yeniden oluşturma';
$language['admin_nav_rebuild_multiple'] = 'Toplu yeniden oluştur';
$language['admin_nav_live_frontend'] = 'Canlı arayüz';
$language['admin_nav_return'] = 'Geri dön';

// fileBan module
$language['file_ban_blocked'] = 'Yükleme başarısız.<br> Dosyaya izin verilmiyor.';
$language['file_ban_btn_title'] = 'Bu dosya hash\'ini yasakla';
$language['file_ban_bd_btn_title'] = 'Dosya hash\'ini yasakla ve sil';
$language['file_ban_invalid_action'] = 'Dosya yasaklama işlemi geçersiz.';
$language['file_ban_invalid_hash'] = 'Sağlanan MD5 hash\'i geçersiz.';
$language['file_ban_add_title'] = 'Dosya yasağı ekle';
$language['file_ban_hash_label'] = 'MD5 hash\'i';
$language['file_ban_added_by_label'] = 'Ekleyen';
$language['file_ban_date_label'] = 'Tarih';
$language['file_ban_delete_label'] = 'Sil';
$language['file_ban_index_title'] = 'Yasaklı dosyalar';
$language['file_ban_no_entries'] = 'Yasaklı dosya yok.';
$language['admin_nav_file_ban_title'] = 'Yasaklı dosyaları yönet';
$language['admin_nav_file_ban'] = 'Dosya yasakları';

// perceptualBan module
$language['perceptual_ban_btn_title'] = 'Bu dosyayı algısal olarak yasakla';
$language['perceptual_ban_bd_btn_title'] = 'Dosyayı algısal olarak yasakla ve sil';
$language['perceptual_ban_invalid_action'] = 'Geçersiz algısal yasaklama işlemi.';
$language['perceptual_ban_invalid_hash'] = 'Geçersiz algısal hash. 16 hex karakter olmalıdır.';
$language['perceptual_ban_no_file'] = 'Dosya belirtilmedi.';
$language['perceptual_ban_file_missing'] = 'Dosya diskte bulunamadı.';
$language['perceptual_ban_hash_failed'] = 'Bu dosya için algısal hash hesaplanamadı.';
$language['perceptual_ban_add_title'] = 'Algısal dosya yasağı ekle';
$language['perceptual_ban_hash_label'] = 'pHash (hex)';
$language['perceptual_ban_added_by_label'] = 'Ekleyen';
$language['perceptual_ban_date_label'] = 'Tarih';
$language['perceptual_ban_delete_label'] = 'Sil';
$language['perceptual_ban_threshold_label'] = 'Hamming mesafesi eşiği';
$language['perceptual_ban_index_title'] = 'Algısal yasaklı dosyalar';
$language['perceptual_ban_no_entries'] = 'Algısal yasaklı dosya yok.';
$language['admin_nav_perceptual_ban_title'] = 'Algısal dosya yasaklarını yönet';
$language['admin_nav_perceptual_ban'] = 'Algısal yasaklar';

// postApi module
$language['post_api_link']                    = 'Post API';
$language['post_api_title']                   = 'Post API rehberi';
$language['post_api_fetching']                = 'Getiriliyor...';
$language['post_api_description']             = 'Kokonotsuba, post verilerini almak için salt okunur bir API sağlar. Tekil postlardan veya tüm thread\'lerden veri çekebilirsiniz.';
$language['post_api_get_single_post']         = 'Tek bir post getir';
$language['post_api_returns_json_post']       = 'Post verileri ve render edilmiş HTML içeren JSON döndürür';
$language['post_api_parameters']              = 'Parametreler';
$language['post_api_th_parameter']            = 'Parametre';
$language['post_api_th_type']                 = 'Tür';
$language['post_api_th_description']          = 'Açıklama';
$language['post_api_th_field']                = 'Alan';
$language['post_api_post_uid_desc']           = 'Postun benzersiz ID\'si';
$language['post_api_response_fields']         = 'Yanıt alanları';
$language['post_api_field_post_uid']          = 'Post benzersiz ID\'si';
$language['post_api_field_timestamp']         = 'Post zaman damgası';
$language['post_api_field_name']              = 'Gönderen adı';
$language['post_api_field_tripcode']          = 'Tripcode (biçimlendirilmiş)';
$language['post_api_field_secure_tripcode']   = 'Güvenli tripcode';
$language['post_api_field_capcode']           = 'Yetkili capcode';
$language['post_api_field_email']             = 'E-posta alanı';
$language['post_api_field_subject']           = 'Post konusu';
$language['post_api_field_comment']           = 'Ham yorum metni';
$language['post_api_field_parent_thread_uid'] = 'Ana thread\'in UID\'si';
$language['post_api_field_parent_post_number'] = 'Thread OP\'sinin post numarası';
$language['post_api_field_html']              = 'Tam render edilmiş post HTML';
$language['post_api_get_thread_posts']        = 'Bir thread\'den postları getir (sayfalandırılmış)';
$language['post_api_returns_json_thread']     = 'Belirtilen thread\'den bir sayfalık postları içeren JSON döndürür. OP her sayfada daima dahil edilir.';
$language['post_api_thread_uid_desc']         = 'Thread\'in benzersiz ID\'si';
$language['post_api_page_param_desc']         = 'Sayfa numarası (0 tabanlı, isteğe bağlı, varsayılan 0)';
$language['post_api_response']                = 'Yanıt';
$language['post_api_thread_response_desc']    = '<code>thread_uid</code>, <code>page</code>, <code>post_count</code> ve <code>posts</code> (tek post endpoint\'i ile aynı alanlara sahip post nesneleri dizisi) içeren bir nesne döndürür.';
$language['post_api_get_thread_list']         = 'Thread listesi getir (sayfalandırılmış)';
$language['post_api_board_uid_desc']          = 'Sayısal board UID\'si (isteğe bağlı, varsayılan mevcut board)';
$language['post_api_returns_json_thread_list'] = 'Board için oluşturulma zamanına göre sıralanmış (en yeni en üstte) sayfalandırılmış thread UID listesi döndürür.';
$language['post_api_thread_list_response_desc'] = '<code>page</code>, <code>threads_per_page</code>, <code>thread_count</code> ve <code>threads</code> (thread nesneleri dizisi) içeren bir nesne döndürür.';
$language['post_api_thread_list_field_thread_uid'] = 'Thread\'in benzersiz ID\'si';
$language['post_api_thread_list_field_subject']    = 'Açılış postunun konusu';
$language['post_api_thread_list_field_last_bump_time'] = 'Son bump zaman damgası';
$language['post_api_thread_list_field_created_time'] = 'Thread\'in oluşturulma zaman damgası';
$language['post_api_thread_list_field_post_count'] = 'Thread içindeki toplam post sayısı';

// fullBanner module
$language['fullbanner_no_file'] = 'Dosya yüklenmedi.';
$language['fullbanner_invalid_link'] = 'Geçersiz hedef bağlantı URL\'si.';
$language['fullbanner_flood'] = 'Lütfen başka bir banner göndermeden önce %1$s saniye bekleyin.';
$language['fullbanner_upload_failed'] = 'Dosya yükleme başarısız.';
$language['fullbanner_invalid_upload'] = 'Geçersiz dosya yükleme.';
$language['fullbanner_invalid_extension'] = 'Yalnızca PNG, JPG, JPEG ve GIF dosyalarına izin verilir.';
$language['fullbanner_invalid_image'] = 'Dosya geçerli bir görsel gibi görünmüyor.';
$language['fullbanner_ext_mime_mismatch'] = 'Dosya uzantısı içerik türüyle eşleşmiyor.';
$language['fullbanner_save_failed'] = 'Yüklenen dosya kaydedilemedi.';
$language['fullbanner_mkdir_failed'] = 'Banner depolama dizini oluşturulamadı.';
$language['fullbanner_invalid_dimensions'] = 'Banner görselleri tam olarak %1$sx%2$s piksel olmalıdır.';
$language['fullbanner_submit_success'] = 'Bu banner gönderildi ve yetkililerin onayını bekliyor!';
$language['fullbanner_req_dimensions'] = 'Görseller tam olarak %1$sx%2$s piksel olmalıdır.';
$language['fullbanner_req_filetypes'] = 'Kabul edilen dosya türleri: PNG, JPG, GIF.';
$language['fullbanner_req_filesize'] = 'Maksimum dosya boyutu: %1$sKB.';
$language['fullbanner_file_too_large'] = 'Dosya boyutu izin verilen maksimum %1$sKB sınırını aşıyor.';
$language['fullbanner_submit_heading'] = 'Banner gönder';
$language['fullbanner_submit_button'] = 'Banner gönder';
$language['fullbanner_upload_heading'] = 'Banner yükle (Otomatik Onaylı)';
$language['fullbanner_upload_button'] = 'Banner yükle';

$language['quick_reply_link'] = 'Hızlı yanıt';
?>















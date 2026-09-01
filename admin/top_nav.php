<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
include "db-conn.php";
// Fetch current admin data for display
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT username, email, first_name, last_name, role, status, 
                       last_login, last_login_ip, created_at 
                       FROM admin_user WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($username, $email, $first_name, $last_name, $role, $status, 
                  $last_login, $last_login_ip, $created_at);
$stmt->fetch();
$stmt->close();
?>
<div class="container-fluid g-0">
        <div class="row">
          <div class="col-lg-12 p-0 ">
            <div class="header_iner d-flex justify-content-between align-items-center">
              <div class="sidebar_icon d-lg-none">
                <i class="ti-menu"></i>
              </div>
              <button type="button" id="sidebarCollapseBtn" class="d-none d-lg-inline-flex align-items-center justify-content-center"
                style="width:36px;height:36px;border:none;background:transparent;color:#fff;font-size:16px;cursor:pointer;margin-right:14px;border-radius:8px;transition:background .15s;"
                onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='transparent'"
                title="Toggle sidebar">
                <i class="ti-menu"></i>
              </button>
              <div class="serach_field-area d-flex align-items-center">
                <div class="search_inner">
                  <form action="#">
                    <div class="search_field">
                      <input type="text" placeholder="Search here...">
                    </div>
                    <button type="submit"> <img src="assets/img/icon/icon_search.svg" alt> </button>
                  </form>
                </div>
                <span class="f_s_14 f_w_400 ml_25 white_text text_white">Apps</span>
              </div>
              <div class="header_right d-flex justify-content-between align-items-center" style="position:relative; right:12px;">
                <div class="header_notification_warp d-flex align-items-center">
                  <li>
                    <a class="bell_notification_clicker nav-link-notify" href="#"> <img src="assets/img/icon/bell.svg"
                        alt>
                    </a>

                    <div class="Menu_NOtification_Wrap">
                      <div class="notification_Header">
                        <h4>Notifications</h4>
                      </div>
                      <div class="Notification_body">

                        <div class="single_notify d-flex align-items-center">
                          <div class="notify_thumb">
                            <a href="#"><img src="assets/img/staf/2.png" alt></a>
                          </div>
                          <div class="notify_content">
                            <a href="#">
                              <h5>Cool Marketing </h5>
                            </a>
                            <p>Lorem ipsum dolor sit amet</p>
                          </div>
                        </div>

                        <div class="single_notify d-flex align-items-center">
                          <div class="notify_thumb">
                            <a href="#"><img src="assets/img/staf/4.png" alt></a>
                          </div>
                          <div class="notify_content">
                            <a href="#">
                              <h5>Awesome packages</h5>
                            </a>
                            <p>Lorem ipsum dolor sit amet</p>
                          </div>
                        </div>

                        <div class="single_notify d-flex align-items-center">
                          <div class="notify_thumb">
                            <a href="#"><img src="assets/img/staf/3.png" alt></a>
                          </div>
                          <div class="notify_content">
                            <a href="#">
                              <h5>what a packages</h5>
                            </a>
                            <p>Lorem ipsum dolor sit amet</p>
                          </div>
                        </div>

                        <div class="single_notify d-flex align-items-center">
                          <div class="notify_thumb">
                            <a href="#"><img src="assets/img/staf/2.png" alt></a>
                          </div>
                          <div class="notify_content">
                            <a href="#">
                              <h5>Cool Marketing </h5>
                            </a>
                            <p>Lorem ipsum dolor sit amet</p>
                          </div>
                        </div>

                        <div class="single_notify d-flex align-items-center">
                          <div class="notify_thumb">
                            <a href="#"><img src="assets/img/staf/4.png" alt></a>
                          </div>
                          <div class="notify_content">
                            <a href="#">
                              <h5>Awesome packages</h5>
                            </a>
                            <p>Lorem ipsum dolor sit amet</p>
                          </div>
                        </div>

                        <div class="single_notify d-flex align-items-center">
                          <div class="notify_thumb">
                            <a href="#"><img src="assets/img/staf/3.png" alt></a>
                          </div>
                          <div class="notify_content">
                            <a href="#">
                              <h5>what a packages</h5>
                            </a>
                            <p>Lorem ipsum dolor sit amet</p>
                          </div>
                        </div>
                      </div>
                      <div class="nofity_footer">
                        <div class="submit_button text-center pt_20">
                          <a href="#" class="btn_1">See More</a>
                        </div>
                      </div>
                    </div>

                  </li>
                  <li>
                    <a class="CHATBOX_open nav-link-notify" href="#"> <img src="assets/img/icon/msg.svg" alt> </a>
                  </li>
                </div>
                <div class="profile_info">
                  <img src="assets/img/client_img.png" alt="#">
                  <div class="profile_info_iner">
                    <div class="profile_author_name">
                      <p><?=$username?> </p>
                      <h5><?=$first_name?> <?=$last_name?></h5>
                    </div>
                    <div class="profile_info_details">
                      <a href="all-admin.php">My Profile </a>
                      <a href="#">Settings</a>
                      <a href="auth/logout.php">Log Out </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
<script>
(function () {
  var KEY = 'rdh_sidebar_collapsed';
  var btn = document.getElementById('sidebarCollapseBtn');
  var sidebar = document.querySelector('.sidebar');
  var content = document.querySelector('.main_content');
  var footer = document.querySelector('.footer_part');
  function apply(collapsed) {
    if (!sidebar || !content) return;
    sidebar.classList.toggle('hide_vertical_menu', collapsed);
    content.classList.toggle('main_content_padding_hide', collapsed);
    if (footer) footer.classList.toggle('pl-0', collapsed);
  }
  try { if (localStorage.getItem(KEY) === '1' && window.innerWidth > 991) apply(true); } catch (e) {}
  if (btn) btn.addEventListener('click', function () {
    var collapsed = !sidebar.classList.contains('hide_vertical_menu');
    apply(collapsed);
    try { localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch (e) {}
  });
})();
</script>
</div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="libs/js/functions.js"></script>

  <!-- ✅ FIXED ACCORDION SIDEBAR TOGGLE SCRIPT -->
<script>
$(document).ready(function(){

    // 1. Dynamic Version Check — Naya deploy/logout hote hi state saaf
    var SIDEBAR_VERSION = "<?php echo defined('APP_VERSION') ? APP_VERSION : 'v2'; ?>";
    
    if(sessionStorage.getItem('sidebar_ver') !== SIDEBAR_VERSION){
        sessionStorage.clear();
        sessionStorage.setItem('sidebar_ver', SIDEBAR_VERSION);
    }

    // 2. Restore ONLY Currently Active Menu
    var activeMenuKey = sessionStorage.getItem('active_submenu');
    if(activeMenuKey){
        var $activeToggle = $('.submenu-toggle[data-menu="' + activeMenuKey + '"]');
        if($activeToggle.length){
            $activeToggle.next('.submenu').show();
            $activeToggle.find('.arrow').addClass('rotate');
        }
    }

    // 3. Strict Accordion Click Event (Ek khulega toh baki saare close)
    $(document).off('click', '.submenu-toggle').on('click', '.submenu-toggle', function(e){
        e.preventDefault();
        e.stopPropagation();

        var $currentToggle = $(this);
        var $currentSubmenu = $currentToggle.next('.submenu');
        var $currentArrow = $currentToggle.find('.arrow');
        var menuKey = $currentToggle.attr('data-menu');

        var isAlreadyOpen = $currentSubmenu.is(':visible');

        // Step A: Close ALL other open submenus strictly
        $('.submenu').not($currentSubmenu).slideUp(200);
        $('.submenu-toggle').not($currentToggle).find('.arrow').removeClass('rotate');

        // Step B: Toggle the clicked menu
        if(isAlreadyOpen){
            $currentSubmenu.slideUp(200);
            $currentArrow.removeClass('rotate');
            sessionStorage.removeItem('active_submenu');
        } else {
            $currentSubmenu.slideDown(200);
            $currentArrow.addClass('rotate');
            sessionStorage.setItem('active_submenu', menuKey);
        }
    });

});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    if(typeof flatpickr !== "undefined" && document.querySelector("#bill_date")) {
        flatpickr("#bill_date", {
            dateFormat: "d/M/Y",
            allowInput: false,
            disableMobile: true
        });
    }

});
</script>

<script>

function deleteGRN(bill){

    Swal.fire({
        title: 'Delete GRN?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {

        if(result.isConfirmed){

            window.location =
            'delete_grn.php?bill=' + encodeURIComponent(bill);

        }

    });

}

</script>
</body>
</html>

<?php if(isset($db)) { $db->db_disconnect(); } ?>

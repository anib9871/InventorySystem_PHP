/* Delete confirmation modal */
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This Supplier Will Be Deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "supplier_master.php?del=" + id;
        }
    });
}
</script>

{{-- Shared theme CSS variables and toggle init script --}}
<style>
:root {
    --brand-primary: #6C4BA0;
    --brand-secondary: #D4A843;
    --brand-dark: #1a1a2e;
    --bg: #f4f4f8;
    --card: #ffffff;
    --card-hover: #f0edf5;
    --muted: #71717a;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --accent: #D4A843;
    --border: #e2e0ea;
    --text-primary: #1a1a2e;
    --text-secondary: #52525b;
    --input-bg: #ffffff;
    --input-border: #d4d4d8;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --table-stripe: rgba(108, 75, 160, 0.03);
}
.dark-mode {
    --brand-dark: #FFFFFF;
    --bg: #0D0A1A;
    --card: #161131;
    --card-hover: #1E1745;
    --muted: #A1A1AA;
    --border: #2A2350;
    --text-primary: #FFFFFF;
    --text-secondary: #A1A1AA;
    --input-bg: #1E1745;
    --input-border: #2A2350;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.4);
    --table-stripe: rgba(255, 255, 255, 0.03);
}
</style>
<script>
(function(){
    var s = localStorage.getItem('dashboard-theme');
    if (s === 'dark') document.documentElement.classList.add('dark-mode');
})();
</script>

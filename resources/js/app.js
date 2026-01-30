import './bootstrap';

document.addEventListener('DOMContentLoaded', function() {
    fetch('/api/dashboard/data')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Données du tableau de bord:', data);
            // You would typically update your dashboard UI here with the fetched data
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des données:', error);
        });
});


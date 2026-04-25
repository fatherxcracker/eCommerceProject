const searchInput   = document.getElementById('live-search');
const searchResults = document.getElementById('search-results');

if (searchInput) {
    let debounceTimer;

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }

        debounceTimer = setTimeout(async () => {
            const res  = await fetch(`/pets/live-search?q=${encodeURIComponent(query)}`);
            const pets = await res.json();

            if (pets.length === 0) {
                searchResults.innerHTML = '<div class="search-dropdown__item">No pets found</div>';
            } else {
                searchResults.innerHTML = pets.map(pet => `
                    <a href="/pets/${pet.id}" class="search-dropdown__item">
                        <img src="${pet.image ?? '/img/default-pet.jpg'}" alt="${pet.name}">
                        <span>${pet.name} <small>${pet.breed}</small></span>
                    </a>
                `).join('');
            }

            searchResults.classList.remove('hidden');
        }, 300);
    });

    document.addEventListener('click', e => {
        if (!searchInput.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });
}

document.querySelectorAll('.flash').forEach(el => {
    setTimeout(() => el.style.opacity = '0', 4000);
    setTimeout(() => el.remove(), 4500);
});

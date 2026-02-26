function initI18n(callback) {
  i18next.init({
    lng: localStorage.getItem('lang') || 'fr',
    resources: {
      fr: { translation: fr },
      en: { translation: en },
      de: { translation: de },
      nl: { translation: nl },
    }
  }, function () {
    updateContent();
    if (callback) callback();
  });
}

function updateContent() {
  // Met à jour html lang=
  document.documentElement.lang = i18next.language;

  // Met à jour tous les textes
  document.querySelectorAll('[data-i18n]').forEach(el => {
    el.textContent = i18next.t(el.getAttribute('data-i18n'));
  });

  // Met à jour les placeholders
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    el.placeholder = i18next.t(el.getAttribute('data-i18n-placeholder'));
  });

  // Met à jour le sélecteur de langue
  const selector = document.getElementById('lang-switcher');
  if (selector) selector.value = i18next.language;
}

function switchLang(lang) {
  localStorage.setItem('lang', lang);
  i18next.changeLanguage(lang, updateContent);
}
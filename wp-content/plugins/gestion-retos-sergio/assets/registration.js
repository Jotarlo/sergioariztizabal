(() => {
  const form = document.querySelector('.saretos-registration');
  if (!form) return;

  const { entry_phone: phone, entry_profile: profile } = form.elements;
  const hosts = {
    tiktok: ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com'],
    instagram: ['instagram.com', 'www.instagram.com', 'm.instagram.com'],
    facebook: ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com', 'fb.watch'],
  };
  const videos = Object.keys(hosts).map((network) => [network, form.elements['entry_video_' + network]]);
  const shortened = ['vm.tiktok.com', 'vt.tiktok.com', 'fb.watch'];

  function isPhone(value) {
    if (!/^\+?[0-9 .()-]{7,32}$/.test(value)) return false;
    let digits = value.replace(/\D/g, '');
    if (!value.startsWith('+')) {
      if (digits.startsWith('00')) digits = digits.slice(2);
      else if (/^[1-9]\d{9}$/.test(digits)) digits = '57' + digits;
      else if (!/^57[1-9]\d{9}$/.test(digits)) return false;
    }
    return digits.startsWith('57') ? /^57[1-9]\d{9}$/.test(digits) : /^[1-9]\d{7,14}$/.test(digits);
  }

  function socialUrl(value, network) {
    try {
      const url = new URL(value);
      if (url.protocol === 'https:' && !url.username && !url.password && !url.port &&
          hosts[network]?.includes(url.hostname)) return url;
    } catch {
      return null;
    }
    return null;
  }

  function isVideo(url, network) {
    if (!url) return false;
    const path = url.pathname;
    if (shortened.includes(url.hostname)) return /^\/[\w-]+\/?$/.test(path);
    if (network === 'tiktok') return /^\/(?:@[\w.-]+\/video\/\d+|t\/[\w-]+)\/?$/.test(path);
    if (network === 'instagram') return /^\/(?:reel|reels|p|tv)\/[\w-]+\/?$/.test(path);
    if (/^\/watch\/?$/.test(path)) return /(?:^|&)v=\d+(?:&|$)/.test(url.search.slice(1));
    return /^\/(?:(?:[\w.-]+\/)?videos\/[\w-]+|reels?\/[\w-]+|share\/[rv]\/[\w-]+)\/?$/.test(path);
  }

  function validate() {
    const phoneValue = phone.value.trim();
    const profileValue = profile.value.trim();
    const supplied = videos.filter(([, input]) => input.value.trim());
    const hasProfile = /^@?[A-Za-z0-9_.-]{1,100}$/.test(profileValue) || supplied.some(([network]) => {
      const url = socialUrl(profileValue, network);
      return url && !shortened.includes(url.hostname) && url.pathname !== '/' && !isVideo(url, network);
    });

    phone.setCustomValidity(phoneValue && !isPhone(phoneValue) ? 'Escribe un número de WhatsApp válido.' : '');
    videos.forEach(([network, input]) => {
      const url = input.value.trim();
      input.setCustomValidity(url && !isVideo(socialUrl(url, network), network)
        ? 'Pega el enlace HTTPS directo a la publicación de esta red.' : '');
    });
    if (!supplied.length) videos[0][1].setCustomValidity('Agrega al menos un enlace directo de publicación.');
    profile.setCustomValidity(profileValue && supplied.length && !hasProfile
      ? 'Escribe tu nombre de usuario o el enlace a tu perfil de una de las redes incluidas.' : '');
  }

  form.addEventListener('input', validate);
  form.addEventListener('change', validate);
  form.addEventListener('submit', (event) => {
    validate();
    if (!form.reportValidity()) event.preventDefault();
  });
})();

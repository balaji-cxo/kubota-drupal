(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.terraGearDealerMap = {
    attach(context) {
      once('terragear-map', '#dealer-map', context).forEach((element) => {
        const key = element.dataset.googleKey;
        if (!key) return;
        let dealers = [];
        try {
          dealers = JSON.parse(element.dataset.dealers || '[]');
        }
        catch {
          return;
        }
        const init = () => {
          const first = dealers[0];
          const map = new google.maps.Map(element, {
            center: first ? { lat: first.latitude, lng: first.longitude } : { lat: 39.1, lng: -84.5 },
            zoom: first ? 9 : 6,
            mapTypeId: 'roadmap',
            mapTypeControl: false,
            streetViewControl: false
          });
          const bounds = new google.maps.LatLngBounds();
          dealers.forEach((dealer) => {
            const position = { lat: dealer.latitude, lng: dealer.longitude };
            const marker = new google.maps.Marker({ map, position, title: dealer.title });
            bounds.extend(position);
            const body = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = dealer.title;
            body.append(title, document.createElement('br'), document.createTextNode(dealer.street + ', ' + dealer.city));
            const info = new google.maps.InfoWindow({ content: body });
            marker.addListener('click', () => info.open({ anchor: marker, map }));
            document.querySelector('[data-dealer-id="' + dealer.id + '"]')?.addEventListener('mouseenter', () => {
              map.panTo(position);
              info.open({ anchor: marker, map });
            });
          });
          if (dealers.length > 1) map.fitBounds(bounds, 48);
          document.querySelectorAll('[data-map-type]').forEach((button) => {
            button.addEventListener('click', () => {
              map.setMapTypeId(button.dataset.mapType);
              document.querySelectorAll('[data-map-type]').forEach((item) => item.classList.toggle('is-active', item === button));
            });
          });
        };
        if (window.google?.maps) {
          init();
        }
        else {
          const script = document.createElement('script');
          script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key);
          script.async = true;
          script.onload = init;
          script.onerror = () => { element.textContent = 'The map could not load. Dealer details remain available in the list.'; };
          document.head.append(script);
        }
      });
    }
  };
})(Drupal, once);

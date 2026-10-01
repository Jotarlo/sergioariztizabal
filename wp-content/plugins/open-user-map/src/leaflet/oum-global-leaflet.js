// Save the fully extended L object to a namespace object to restore it to the global scope later (prevents conflicts with other Leaflet instances)
if (!window.OUMLeaflet) {
  window.OUMLeaflet = {};
}
window.OUMLeaflet.L = window.L;
// Detect project root dynamically so the site works whether it is deployed at
// the web root (http://example.com/) or in a subdirectory
// (http://example.com/kapada/).  The frontend is always one level inside the
// project root at /…/frontend/, so we walk back to that boundary.
(function () {
  var pathname = window.location.pathname;
  var idx = pathname.lastIndexOf('/frontend/');
  var projectBase = idx !== -1
    ? window.location.origin + pathname.substring(0, idx)
    : window.location.origin;
  window.API_BASE    = projectBase + '/backend/api';
  window.UPLOAD_BASE = projectBase + '/uploads';
}());

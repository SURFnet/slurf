async function checkAvailability(nickname) {
    if(nickname.length < 2) return;
    fetch('nickname/exists/' + encodeURIComponent(nickname))
    .then(response => {
	 return response.json();
     })
    .then(responseAsJson => {
         updateForm(responseAsJson);
     });
}

function updateForm(data) {
  if(data == 'invalid') {
    showElt('nickname-invalid');
    showElt('nickname-free', false);
    showElt('nickname-taken', false);
    return;
  }
  if(data == 'free') {
    showElt('nickname-invalid', false);
    showElt('nickname-free');
    showElt('nickname-taken', false);
    return;
  }
  if(data == 'taken') {
    showElt('nickname-invalid', false);
    showElt('nickname-free', false);
    showElt('nickname-taken');
    return;
  }
}

function showElt(idname, show = true) {
  var x = document.getElementById(idname);
  x.style.display = show ? "block" : "none";
}

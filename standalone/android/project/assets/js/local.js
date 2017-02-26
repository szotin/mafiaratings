var local = new function()
{
	this.init = function()
	{
/*		http.host("http://mafiaratings.com/");
		mafia.stateChange(mafia.ui.sync);
		mafia.dirtyEvent(function(dirty) { $('#save').prop('disabled', !dirty); mafia.ui.updateButtons(); });
		mafia.failEvent(function(message) { dlg.error(message); });*/
		
		function onDeviceReady()
		{
			console.log('onDeviceReady');
			window.requestFileSystem(LocalFileSystem.PERSISTENT, 0, onFileSystemSuccess, fail);
		}

		function onFileSystemSuccess(fileSystem)
		{
			console.log('onFileSystemSuccess');
			fileSystem.root.getFile("data", null, gotFileEntry, fail);
		}

		function gotFileEntry(fileEntry)
		{
			console.log('gotFileEntry');
			fileEntry.file(gotFile, fail);
		}

		function gotFile(file)
		{
			console.log('gotFile');
			readAsText(file);
		}

		function readAsText(file)
		{
			console.log('readAsText');
			var reader = new FileReader();
			reader.onloadend = function(evt)
			{
				console.log(evt.target.result);
			};
			reader.readAsText(file);
		}		

		function fail(evt)
		{
			console.log('fail');
			for (var p in evt.target.error)
			{
				console.log(p + ': ' + evt.target.error[p]);
			}
			dlg.error(evt.target.error.code);
		}
				
		console.log(')))))))))))))))');
		document.addEventListener("deviceready", onDeviceReady, false);
	}
	
	this.sync = function()
	{
/*		mafia.loadClubs(function () { selectClubForm.show(mafia.sync); });*/
	}
}
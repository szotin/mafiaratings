// Storage represents abstract storage. The public interface is:
// var storage = new Storage();
// storage.read("myFile.txt", readSuccess, readFail);
// storage.write("aaa", "bla-bla-bla", writeSuccess, writeFail);
// storage.remove("garbage.png");
// 
// The physical storage depends on the implementation

// This one represents local file system
function Storage()
{
	var fileSystem;
	this.error = null;
	
	function gotFS(fs)
	{
		fileSystem = fs;
	}
	
	function fail(event)
	{
		console.log(event.target.error.code);
		this.error = event.target.error;
	}
	
	window.requestFileSystem(LocalFileSystem.PERSISTENT, 0, gotFS, fail);
	
	this.read = function(onSuccess, onFail, name)
	{
		function fail(event)
		{
			console.log(event.target.error.code);
			if (onFail != null)
			{
				onFail(L("ErrReadFile", name));
			}
		}
		
		function success(event)
		{
			if (onSuccess != null)
			{
				onSuccess();
			}
		}
		
		function gotFile(file)
		{
			var reader = new FileReader();
			reader.onloadend = success;
			reader.readAsText(file);
		}
		
		function gotFileEntry(fileEntry)
		{
			fileEntry.file(gotFile, fail);
		}
		
		fileSystem.root.getFile(name, null, gotFileEntry, fail);
	}
	
	this.write = function(onSuccess, onFail, name, data)
	{
		function fail(event)
		{
			console.log(event.target.error.code);
			if (onFail != null)
			{
				onFail(L("ErrWriteFile", name));
			}
		}
		
		function success(event)
		{
			if (onSuccess != null)
			{
				onSuccess();
			}
		}
		
		function gotFileWriter(writer)
		{
			writer.onwrite = success;
			writer.write(data);
		}
		
		function gotFileEntry(fileEntry)
		{
			fileEntry.createWriter(gotFileWriter, fail);
		}
		
		fileSystem.root.getFile(name, {create: true}, gotFileEntry, fail);
	}
	
	this.remove = function(onSuccess, onFail, name)
	{
		function fail(event)
		{
			console.log(event.target.error.code);
			if (onFail != null)
			{
				onFail(L("ErrDeleteFile", name));
			}
		}
		
		function success(entry)
		{
			if (onSuccess != null)
			{
				onSuccess();
			}
		}
		
		function gotFileEntry(fileEntry)
		{
			fileEntry.remove(success, fail);
		}
		
		fileSystem.root.getFile(name, null, gotFileEntry, fail);
	}
}

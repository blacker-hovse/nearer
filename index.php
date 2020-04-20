<?php
include(__DIR__ . '/../lib/include.php');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <?php print_head('Nearer'); ?>
  </head>
  <body>
    <div id="main">
      <h1>Nearer</h1>
<?php

$subtitles = array(
  'Beats Ricketts Music',
  "Don't Play the Ride",
  'Play Loud, Play Proud',
  'The Day the Music Died',
  'Better Than Ever Before',
  'New and Improved!',
  'Memers Will Be Shot'
);

$subtitle = $subtitles[mt_rand(0, count($subtitles) - 1)];

echo <<<EOF
      <h2>$subtitle</h2>
EOF;
?>
      <style>
	html, body {
	  overflow-x: hidden;
	}
	.form-control {
	  max-width: 95%;
	}
	.control {
          display: inline-block;
          margin: 0.4em;
          border: 1px solid #111;
          border-radius: 0.3em;
          outline: 0;
          padding: 0.2em 0.6em;
          width: auto;
          background-color: #222;
          color: #ccc;
          text-decoration: none;
          font-size: 0.9em;
          line-height: 2;
          cursor: default;
        }
        .control:hover {
          border-color; #222;
          background-color: #333;
          color: #ccc;
        }
        .control:active {
          border-color: #222;
          background-color: #111;
          color: #999;
        }
        .control.disabled {
          border-color: #444;
          background-color: #444;
          color: #999;
	}
	.mediasp {
          margin: 0;
          padding: 0;
        }
        .mediasp > .pull-left {
	  position: relative;
	  width: 30%;
	  top: -2em;
	  right: 0%;
	  bottom: 0;
	  /*! left: 0; */
	  margin-left: 0;
	}
	.mediasp:after {
          content: "";
          display: block;
          clear: both;
	}
	.song {
	  display: flex;
	  border-bottom: 1px solid #222;
	  margin-top: 5px;
	  padding-bottom: 5px;
	  overflow: hidden;
	  max-width: 100vw;
	}
	.song div {
	  margin-left: 1.2em;
	  margin-top: 0.5em;
	}
	.song div > * {
	  margin: 0;
	}
      </style>

      <script>
      let API_KEY = 'AIzaSyAjsH3NhiqjpoyNjTLh8exVtEcHVuwVYKI';
      async function get_video_data(ids) {
	if (ids == null) {
          return null;
        }

	let id_list = ids.join(',');

        let res = await fetch(`https://content.googleapis.com/youtube/v3/videos?id=${id_list}&part=snippet&key=${API_KEY}`);
        let json = await res.json();
	console.log(json.items);
	let videos = {};
	json.items.forEach(
          item => {
	    videos[item.id] = {
	      author_name: item.snippet.channelTitle,
	      author_url: `https://youtube.com/channel/${item.snippet.channelId}`,
	      title: item.snippet.title,
	      thumbnail: item.snippet.thumbnails.medium.url,
              url: `https://youtu.be/${item.id}`
            };
          }
	);
	return videos;
      }
      async function update() {
        console.log('updating...');
        let status = await fetch('https://blacker.caltech.edu/nearer/process.php?status', {
          credentials: 'include',
	});
	let data = await status.json();
	console.log(data);
        
	let song_div_inner = '';
	  
	let songs = [];
	if (data.history) {
	  let song_data = await get_video_data(data.history.map(x => x != null ? x.vid : null));
	  songs = songs.concat(data.history.map((song, i) => song == null ? null : Object.assign({ note: song.note, added_by: song.user, added_on: song.time }, song_data[song.vid])));
	  songs.push('divider');
	}
	if (data.queue) {
	  let song_data = await get_video_data(data.queue.map(x => x != null ? x.vid : null));
	  let new_songs = data.queue.map((song, i) => song == null ? null : Object.assign({ note: song.note, added_by: song.user, added_on: song.time }, song_data[song.vid]));
	  new_songs.reverse();
	  songs = songs.concat(new_songs);
	}	
	if (data.current) songs.push(data.current);
	songs.reverse();

	songs.forEach((song) => {
	  if (song != null && song !== 'divider') {
            let song_element = `<div class="song">
              <div style="width: 30%"><img src="${song.thumbnail}" style="min-width: 100%; max-width: 100%;" /></div>
                <div>
                  <h4><a href="${song.url}">${song.title}</a></h4>
                  <p>Uploaded by <a href="${song.author_url}">${song.author_name}</a></p>
                  <p>Added by ${song.added_by} on ${song.added_on}</p>
                  <p>${song.note.replace('<', '&lt;')}</p>
                </div>
              </div>`;
	    song_div_inner += song_element;
	  } else if (song != null) {
            song_div_inner += '<h2 style="border-top:none">History</h2>';
	  }
        });
	document.getElementById('recently_added').innerHTML = song_div_inner;
	  
        document.getElementById('client_status').innerHTML = data.client_connected ? `Client connected and ${data.status}` : `Client disconnected.`;

	if (data.current) {
	  let song = data.current;
	  document.getElementById('playing_now').innerHTML = `\
            <div class="song" style="border-bottom: none">
              <div style="width: 30%"><img src="${song.thumbnail}" style="max-width: 100%;" /></div>
                <div>
                  <h4><a href="${song.url}">${song.title}</a></h4>
                  <p>Uploaded by <a href="${song.author_url}">${song.author_name}</a></p>
                  <p>Added by ${song.added_by} on ${song.added_on}</p>
                  <p>${song.note}</p>
                </div>
              </div>`;
	} else {
            document.getElementById('playing_now').innerHTML = `<h3 style="text-align: center">No Song Playing.</h3>`;
        }
      }

      function get_req(action) {      
	fetch(`https://blacker.caltech.edu/nearer/process.php?action=${action}`, {
          credentials: 'include',
        }).then(update());
      }

      let lock = false;

      function submit_song() {
        if (!lock) {
          lock = true;

          let url = $('#url').val();
          let note = $('#note').val();

          let formData = new FormData();
          formData.append('url', url);
          formData.append('note', note);
 
          fetch('https://blacker.caltech.edu/nearer/process.php', {
            credentials: 'include',
            method: 'POST',
            body: formData,
          }).then((res) => {
            lock = false;
	    update();
	    if (res.status === 200) {
              res = res.json();

              $('#url').val('');
              $('#note').val('');

              $('#success_div').css('display', '');
              setTimeout(() => { $('#success_div').css('display', 'none') }, 5000);
            } else {
              $('#error_code').text(res.status);
              $('#failure_div').css('display', '');
              setTimeout(() => { $('#failure_div').css('display', 'none') }, 5000);
              res.json().then((resp) => {$("#error_message").text(resp.message);});
            }
          });
        }
      }
      </script>

      <div id="success_div" class="success" style="display: none">
          Success! Song added to queue.
      </div>
      <div id="failure_div" class="error" style="display: none">
          Error! Code: <a id="error_code"></a> Message: <a id="error_message"></a>
      </div>
      <form onsubmit="submit_song(); return false;">
        <div class="form-control">
          <label for="url">YouTube URL</label>
          <div class="input-group">
            <input type="text" id="url" name="url" />
          </div>
        </div>
        <div class="form-control optional">
          <label for="note">Note</label>
          <div class="input-group">
            <input type="text" id="note" name="note" maxlength="255" />
          </div>
        </div>
        <div class="form-control">
          <div class="input-group">
            <button type="submit" class="control">Submit</button>
            <div class="pull-right">
              <button class="control" onclick="get_req('resume')" type="button">&nbsp;&#9654;&nbsp;</button>
              <button class="control" onclick="get_req('skip')" type="button">&nbsp;&#9197;&nbsp;</button>
              <button class="control" onclick="get_req('pause')" type="button">&nbsp;&#9724;&nbsp;</button>
            </div>
          </div>
	</form>
	<h3 id="client_status" style="text-align:center; margin: 1.2em auto 0"></h3>
      </div>
      
      <h2>Playing Now</h2>
      <div id="playing_now">
      
      </div>

      <h2>Upcoming</h2>
      <div id="recently_added">

      </div>

      <script>
      update();
      let updateInterval = setInterval(update, 30000);
      </script>

<?php
print_footer(
  'Copyright &copy; 2018 Ethan Jaszewski, Will Yu',
  'A service of Blacker House'
);
?>  </body>
</html>

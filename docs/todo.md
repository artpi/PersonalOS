These are roughly the features I want to implement. They are meant to be 

## Properly scoped

- In modules/openai/class-openai-module.php, the create_system_prompt() is getting the post content and converting it to markdown. It should parse Gutenberg blocks properly and retrieve certain details. For example, the `core/audio` block should be filtered and the audio shoudl be returned as an attribute.
- In modules/openai/class-pos-ai-podcast-module.php, use the soundtrack_url from the original prompt (stored as prompt_id) to return as the background music.
- In modules/evernote/class-evernote-module.php, there is a tool to get a random evernote not. We need to introduce a similar tool to get readwise highligts as well as a random readwise highlight from a random article/book. Once this is a proper AI tool, then we can use it as a part of a prompt.
- Implement a module, exposing a tool to use Google Custom Search tool to search the web. Here are some docs: https://developers.google.com/custom-search/v1/overview . Then you can `GET https://www.googleapis.com/customsearch/v1`. The same module probably should also expose a tool to retrieve an arbitrary URL content.
- We need a new Gutenberg block for to-dos, which can be embedded in any note. When a new block is inserted into the note, then the to-do gets created on the backend, similarly to the note block. When it gets edited, then the to-do gets updated. The todos need to be aware of the notes they are embedded in.
- Rewrite starter-content files to be pinned to a version.
- When todos are rescheduled in modules/todo/class-todo-module.php, The event is not rescheduled. We need to fix this.
  

## Need Scoping / plan

- The permissions are a mess. Everybody has access to everything - We need to properly review API access and permissions to distinguish between and public / private content.
- We need proper Unit Testing for everything
- Notes: Note should be attachable to URLs. Then we can write a Chrome plugin or something similar to display "notes" once URL is visited.
- We need a tool to send emails
- Docs: Write cursor rules and docs explaining how to create a new module and a tool.
- Openverse has some good epic soundtracks for the background of motivational prompt: https://openverse.org/search/audio?q=epic+cinematic+motivational
- Need to implement UX for Notebooks, similar to how Todos are using Data Views. Then implement a dashboard for Bucketlists.
- Need integration with Google Calendar, Google Docs, Google Drive and other google services to sync data.
- Once we have calendar, we can scan upcoming trips and events to remind about opportunities to do the stuff on my bucketlist.
- "Central" notes for a notebook that would link to most important notes and content. It could also use AI to automatically link new notes.
- Year review dashboard
- Week Review dashboard
- Daily dashboard / Daily note: Does it need a seperate dashboard?
- The whole thing needs proper search, but this is something to get Jetpack to do through Jetpack search. WASM is promising, but it's not gonna help agents.
- Backlinks for notes
- Sync with Strava: The main question is if Strava should put the workout details in the daily node or should it use some other data structure?
- Management UX for good questions and integrate them into the whole system. They should probably be collected in a notebook as notes
- Implement one of the open source chatbot UXs
- Probably need to implement a new post type, which is a log, that would include all workouts and similar events like Google Photos, workouts, done to-dos, and then the daily notes would list these log items inside. The reason for this being a separate custom post type is that if we put this in the notes, we would have crazy conflicts during the day when the daily note is being generated. If we have it separately, we have an easier way to resolve those conflicts.


## Crazy ideas

- It would be awesome if we had AI-generated songs via the UDIO API or something, but the API is not there yet. Imagine AI generated songs for your todo lists.
- Watchers: New information is processed according to rules - tags them etc
- Personal CRM: Sync with Google Contacts. Track of birthdays, kids' names and how you can enrich the lives of the people around you.
- local CLI: Somehow expose tools as CLI commands to be executed locally for helpers and such. WP-CLI is promising, but it would be neat to make it work against the remote database.
- Habits support
- Tools: You should be able to delegate a TODO to AI so that an "Agent" picks it up and does research / scoping or other help. For scheduled todos it would make a lot of sense like when AI should remind somebody about something.
- "Your Crew": AI agents cheering you on with comments when you are building habits and stuff.
- Somehow describe procedures and prompts as spellbooks
- Engineer serendipity in some way
- Talk to other instances on other WordPress sites using @akirk Friends plugin
- Sync with [Google Tasks API](https://developers.google.com/tasks/reference/rest)
- When I am attaching an email, incorporate that into the knowledge base some way. For example, incorporate fresh thinking into a document
- 
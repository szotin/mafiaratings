using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Collections;

namespace MafiaRatings.GameObjects
{
    public class UserRef : IComparable<UserRef>
    {
        private User mUser;
        private string mName;

        public UserRef(User user, string byNick = null)
        {
            mUser = user;
            if (byNick != null && byNick.Length > 0 && byNick != user.Name)
            {
                mName = byNick + " (" + user.Name + ")";
            }
            else
            {
                mName = user.Name;
            }
        }

        public User User { get { return mUser; } }
        public string Name { get { return mName; } }

        public override string ToString()
        {
            return mName;
        }

        public int CompareTo(UserRef user)
        {
            return mName.CompareTo(user.mName);
        }
    }


    public class UserList : IEnumerable
    {
        private Dictionary<int, User> mUsers = new Dictionary<int, User>();

        public UserList()
        {
            User dummy = new User();
            mUsers.Add(dummy.Id, dummy);
        }

        public UserList(DataReader reader, int version = Database.VERSION)
            : this()
        {
            int count = reader.NextInt();
            for (int i = 0; i < count; ++i)
            {
                User user = new User(reader, version);
                mUsers.Add(user.Id, user);
            }
        }

        public void Write(DataWriter writer)
        {
            writer.Add(mUsers.Count - 1);
            foreach (User user in mUsers.Values)
            {
                if (!user.IsDummy)
                {
                    user.Write(writer);
                }
            }
        }

        private void AddUser(List<UserRef> users, User user, Party party, string lowerFilter)
        {
            if (user.IsDummy)
            {
                return;
            }

            if (party != null && party.IsUserRegistered(user.Id))
            {
                return;
            }

            if (lowerFilter.Length == 0)
            {
                users.Add(new UserRef(user));
                return;
            }

            string name = user.Name.ToLower();
            if (name.StartsWith(lowerFilter))
            {
                users.Add(new UserRef(user));
            }

            foreach (UserNick userNick in user.Nicks)
            {
                string nick = userNick.Nick.ToLower();
                if (nick != name && nick.StartsWith(lowerFilter))
                {
                    users.Add(new UserRef(user, userNick.Nick));
                }
            }
        }

        public List<UserRef> Query(string filter, int partyId, bool queryServer)
        {
            List<UserRef> users = new List<UserRef>();
            Party party = Database.Parties[partyId];
            if (filter == null)
            {
                filter = "";
            }
            filter = filter.ToLower();

            // local query
            foreach (User user in mUsers.Values)
            {
                if (user.IsClubMember)
                {
                    AddUser(users, user, party, filter);
                }
            }

            // server request
            if (queryServer && Database.Gate.Connected)
            {
                Gate.Request request = new Gate.Request(Database.Gate, Gate.Request.Code.GetUserList);
                request.Add(partyId);
                request.Add(filter);

                Gate.Response response = request.Send();
                int count = response.NextInt();
                for (int i = 0; i < count; ++i)
                {
                    AddUser(users, new User(response), party, filter);
                }
            }
            users.Sort();
            return users;
        }

        public int Count
        {
            get { return mUsers.Count; }
        }

        public User this[int i]
        {
            get
            {
                User user = null;
                mUsers.TryGetValue(i, out user);
                return user;
            }
        }

        public bool Contains(int id)
        {
            return mUsers.ContainsKey(id);
        }

        public IEnumerator GetEnumerator()
        {
            return mUsers.Values.GetEnumerator();
        }

        public User AddUser(Gate.Response response)
        {
            User user = new User(response);
            mUsers[user.Id] = user;
            return user;
        }

        public void AddUser(User user)
        {
            mUsers[user.Id] = user;
        }
    }
}

using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Globalization;

namespace MafiaRatings.GameObjects
{
    public class Party : IComparable<Party>
    {
        private int mId;
        private string mName;
        private int mStartTime;
        private int mDuration;
        private int mLanguages;
        private int mFlags;
        private RegistrationList mRegistrations;
        private int mRulesId;

        private const int FLAG_REG_ON_ATTEND = 1;
        private const int FLAG_PWD_REQUIRED = 2;
        private const int FLAG_CANCELED = 4;
        private const int FLAG_ALL_MODERATE = 8;

        public Party()
        {
            mId = -Database.Club.Id;
            mRulesId = Database.Club.RulesId;
            mName = Strings.NoEvent;
            mStartTime = 0;
            mDuration = int.MaxValue;
            mLanguages = Language.ALL;
            mFlags = FLAG_PWD_REQUIRED;
            mRegistrations = new RegistrationList(this);
        }

        public Party(DataReader reader, int version = Database.VERSION)
        {
            mId = reader.NextInt();
            if (mId > 0)
            {
                mName = reader.NextString();
                mStartTime = reader.NextInt();
                mDuration = reader.NextInt();
                if (version > 1)
                {
                    mRulesId = reader.NextInt();
                }
                else
                {
                    mRulesId = Database.Club.RulesId;
                }
            }
            else
            {
                mName = Strings.NoEvent;
                mStartTime = 0;
                mDuration = int.MaxValue;
                mRulesId = Database.Club.RulesId;
            }
            mLanguages = reader.NextInt();
            mFlags = reader.NextInt();
            mRegistrations = new RegistrationList(this, reader, version);
        }

        public void Write(DataWriter writer)
        {
            writer.Add(mId);
            if (mId > 0)
            {
                writer.Add(mName);
                writer.Add(mStartTime);
                writer.Add(mDuration);
                writer.Add(mRulesId);
            }
            writer.Add(mLanguages);
            writer.Add(mFlags);
            mRegistrations.Write(writer);
        }

        private void CheckRegistrationNick(string nickName)
        {
/*            string lNickName = nickName.ToLower();
            foreach (Registration reg in mRegistrations)
            {
                if (lNickName == reg.Nickname.ToLower())
                {
                    throw new GameException("Nick name " + nickName + " is already used by another user");
                }
            }*/
        }

        public Registration RegisterUser(User user, string nickName, int duration, string password, bool makeMember)
        {
            CheckRegistrationNick(nickName);

            bool passwordRequired = true;
            if (user.IsClubMember)
            {
                makeMember = false;
                passwordRequired = PasswordRequired;
            }

            Registration reg;

            Gate.Request request = new Gate.Request(Database.Gate, Gate.Request.Code.RegisterUser);
            request.Add(user.Id);
            request.Add(mId);
            request.Add(nickName);
            request.Add(makeMember);
            if (mId <= 0)
            {
                request.Add(duration);
            }
            else
            {
                request.Add(string.Empty);
            }
            if (passwordRequired)
            {
                request.Add(Gate.MD5(password));
            }
            else
            {
                request.Add(string.Empty);
            }

            if (Database.Gate.Connected)
            {
                request.Add(true);
                Gate.Response response = request.Send();
                if (makeMember)
                {
                    user.IsClubMember = true;
                }
                Database.Users.AddUser(user);
                reg = new Registration(this, response);
            }
            else if (!passwordRequired)
            {
                request.Add(false);
                Database.AddDelayedRequest(request);
                reg = new Registration(this, user.Id, nickName, duration);
            }
            else
            {
                throw new GameException(Strings.ErrLoginRequredForReg);
            }
            mRegistrations.Add(reg);
            Database.Save();
            return reg;
        }

        public Registration RegisterNewUser(
            string userName,
            string email,
            int gender,
            string nickName,
            int duration)
        {
            if (!Database.Gate.Connected)
            {
                throw new GameException(Strings.ErrLoginRequiredForNewUser);
            }

            CheckRegistrationNick(nickName);

            Gate.Request request = new Gate.Request(Database.Gate, Gate.Request.Code.RegisterNewUser);
            request.Add(mId);
            request.Add(userName);
            request.Add(email);
            request.Add(gender);
            request.Add(nickName);
            if (mId <= 0)
            {
                request.Add(duration);
            }
            else
            {
                request.Add(string.Empty);
            }

            Gate.Response response = request.Send();
            Database.Users.AddUser(response);

            Registration reg = new Registration(this, response);
            mRegistrations.Add(reg);
            return reg;
        }

        public List<Registration> QueryPlayers()
        {
            List<Registration> list = new List<Registration>();
            if (mId < 0)
            {
                int now = Timestamp.Now;
                foreach (Registration reg in mRegistrations)
                {
                    if (reg.StartTime <= now && reg.StartTime + reg.Duration > now && reg.CanPlay)
                    {
                        list.Add(reg);
                    }
                }
            }
            else
            {
                foreach (Registration reg in mRegistrations)
                {
                    if (reg.CanPlay)
                    {
                        list.Add(reg);
                    }
                }
            }
            list.Sort();
            return list;
        }

        public List<Registration> QueryModerators()
        {
            List<Registration> list = new List<Registration>();
            if (mId < 0)
            {
                int now = Timestamp.Now;
                foreach (Registration reg in mRegistrations)
                {
                    if (reg.StartTime <= now && reg.StartTime + reg.Duration > now && reg.CanModerate)
                    {
                        list.Add(reg);
                    }
                }
            }
            else
            {
                foreach (Registration reg in mRegistrations)
                {
                    if (reg.CanModerate)
                    {
                        list.Add(reg);
                    }
                }
            }
            list.Sort();
            return list;
        }

        public bool IsUserRegistered(int userId)
        {
            return mRegistrations.Contains(userId);
        }

        public int Id
        {
            get { return mId; }
        }

        public string Name
        {
            get { return mName; }
        }

        public int StartTime
        {
            get { return mStartTime; }
        }

        public int Duration
        {
            get { return mDuration; }
        }

        public int Languages
        {
            get { return mLanguages; }
        }

        public int RulesId
        {
            get { return mRulesId; }
        }

        public override string ToString()
        {
            if (mId > 0)
            {
                return mName + ": " + Timestamp.Convert(mStartTime).ToString("dddd MMMM d, yyyy");
            }
            return mName;
        }

        public int CompareTo(Party other)
        {
            if (mId <= 0)
            {
                if (other.Id <= 0)
                {
                    return 0;
                }
                else
                {
                    return -1;
                }
            }
            else if (other.mId <= 0)
            {
                return 1;
            }

            if (mStartTime < other.mStartTime)
            {
                return -1;
            }
            else if (mStartTime > other.mStartTime)
            {
                return 1;
            }
            return 0;
        }

        public bool RegisterOnAttend
        {
            get { return (mFlags & FLAG_REG_ON_ATTEND) != 0; }
        }

        public bool PasswordRequired
        {
            get { return (mFlags & FLAG_PWD_REQUIRED) != 0; }
        }

        public bool Canceled
        {
            get { return (mFlags & FLAG_CANCELED) != 0; }
        }

        public bool AllCanModerate
        {
            get { return (mFlags & FLAG_ALL_MODERATE) != 0; }
        }
    }
}

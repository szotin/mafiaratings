using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.IO;
using System.Windows.Forms;

namespace MafiaRatings.GameObjects
{
    public static class Database
    {
        public const int VERSION = 2;
        private const string DATA_FILE_NAME = "data.txt";
        private const char SEPARATOR = '|';

        private static string mDataDir;

        private static bool mConnectOnStartup;
        private static string mPasswordKey;
        private static PartyList mParties;
        private static UserList mUsers;
        private static GameRulesList mRules;
        private static Gate mGate = new Gate();
        private static Game mGame;

        private static string mHostUrl;
        private static string mUserName;
        private static int mUserId;
        private static int mUserFlags;
        private static Club mClub;

        private static List<string> mDelayedRequests;

        public static string DataDir
        {
            get
            {
                if (mDataDir == null)
                {
                    throw new GameException(Strings.ErrDbInit);
                }
                return mDataDir;
            }

            set
            {
                if (mDataDir == value)
                {
                    return;
                }

                if (!value.EndsWith(Path.DirectorySeparatorChar.ToString()))
                {
                    value += Path.DirectorySeparatorChar;
                }
                mDataDir = value;

                bool canLoad = true;
                try
                {
                    Directory.CreateDirectory(value);
                }
                catch (Exception)
                {
                    canLoad = false;
                }

                mDelayedRequests = null;
                string fileName = value + DATA_FILE_NAME;
                if (canLoad && File.Exists(fileName))
                {
                    DataReader reader = new DataReader(File.ReadAllText(fileName), SEPARATOR);
                    int version = reader.NextInt();
                    if (version > VERSION)
                    {
                        throw new Exception(Strings.ErrDataVersion);
                    }
                    mHostUrl = Gate.DEFAULT_HOST_URL;
                    if (version > 0)
                    {
                        mHostUrl = reader.NextString();
                    }
                    mUserId = reader.NextInt();
                    mUserName = reader.NextString();
                    mUserFlags = reader.NextInt();
                    mPasswordKey = reader.NextString();
                    mConnectOnStartup = reader.NextBool();
                    mClub = new Club(reader, version);
                    mUsers = new UserList(reader, version);
                    mParties = new PartyList(reader, version);
                    mRules = new GameRulesList(reader, version);
                    mGame = new Game();
                }
                else
                {
                    mClub = new Club();
                    mUsers = new UserList();
                    mParties = new PartyList();
                    mRules = new GameRulesList();
                    mUserId = -1;
                    mUserName = Strings.UnknownUser;
                    mUserFlags = 0;
                    mGame = new Game();
                    Random rnd = new Random();
                    mPasswordKey = RandomString(rnd, rnd.Next(8, 12));
                    mConnectOnStartup = true;
                    mHostUrl = Gate.DEFAULT_HOST_URL;
                }
            }
        }

        public static string RandomString(Random rnd, int size)
        {
            StringBuilder str = new StringBuilder(size);
            for (int i = 0; i < size; ++i)
            {
                int index = rnd.Next(0, 26 + 26 + 10);
                if (index < 26)
                {
                    str.Append((char)('a' + index));
                }
                else if (index < 26 + 26)
                {
                    str.Append((char)('A' + index - 26));
                }
                else
                {
                    str.Append((char)('0' + index - 26 - 26));
                }
            }
            return str.ToString();
        }

        public static string RandomString(int size)
        {
            return RandomString(new Random(), size);
        }

        public static void Save()
        {
            DataWriter writer = new DataWriter(SEPARATOR);
            writer.Add(VERSION);
            writer.Add(mHostUrl);
            writer.Add(mUserId);
            writer.Add(mUserName);
            writer.Add(mUserFlags);
            writer.Add(mPasswordKey);
            writer.Add(mConnectOnStartup);
            mClub.Write(writer);
            mUsers.Write(writer);
            mParties.Write(writer);
            mRules.Write(writer);
            File.WriteAllText(DataDir + DATA_FILE_NAME, writer.Data);
        }

        public static PartyList Parties
        {
            get { return mParties; }
        }

        public static UserList Users
        {
            get { return mUsers; }
        }

        public static GameRulesList Rules
        {
            get { return mRules; }
        }

        public static Gate Gate
        {
            get { return mGate; }
        }

        public static Game Game
        {
            get { return mGame; }
        }

        public static bool CanSynchronize
        {
            get
            {
                if (mGame.State != GameState.NotStarted)
                {
                    return false;
                }

                if (!mGate.Connected)
                {
                    return false;
                }
                return true;
            }
        }

        public static bool Synchronize()
        {
            if (CanSynchronize)
            {
                string userName = mUserName;
                int userId = mUserId;
                int userFlags = mUserFlags;
                List<string> delayedRequests = mDelayedRequests;
                Club club = mClub;
                string hostUrl = mHostUrl;
                UserList users = mUsers;
                PartyList parties = mParties;
                GameRulesList rules = mRules;
                try
                {
                    mDelayedRequests = null;
                    mUserName = mGate.UserName;
                    mUserId = mGate.UserId;
                    mUserFlags = mGate.UserFlags;
                    mClub = mGate.Club;
                    mHostUrl = Gate.HostUrl;

                    SendDelayedRequests();

                    Gate.Request request = new Gate.Request(mGate, Gate.Request.Code.DownloadData);
                    request.Add(mClub.Id);

                    Gate.Response response = request.Send();
                    mUsers = new UserList(response);
                    mParties = new PartyList(response);
                    mRules = new GameRulesList(response);

                    mGame.UpdateData();
                    Save();
                }
                catch (Exception)
                {
                    mUserName = userName;
                    mUserId = userId;
                    mUserFlags = userFlags;
                    mDelayedRequests = delayedRequests;
                    mClub = club;
                    mHostUrl = hostUrl;
                    mUsers = users;
                    mParties = parties;
                    mRules = rules;
                    Gate.Logout();
                    throw;
                }
                return true;
            }
            return false;
        }

        public static int UserId
        {
            get { return mUserId; }
        }

        public static string UserName
        {
            get { return mUserName; }
        }

        public static int UserFlags
        {
            get { return mUserFlags; }
        }

        public static string PasswordKey
        {
            get { return mPasswordKey; }
        }

        public static bool ConnectOnStartup
        {
            get { return mConnectOnStartup; }
            set
            {
                if (value != mConnectOnStartup)
                {
                    mConnectOnStartup = value;
                    Save();
                }
            }
        }

        private static string DelayedRequestsFilename
        {
            get
            {
                return DataDir + Gate.MD5((mHostUrl + mUserName).ToLower()) + ".drq";
            }
        }

        private static List<string> DelayedRequests
        {
            get
            {
                if (mDelayedRequests == null)
                {
                    mDelayedRequests = new List<string>();
                    string fileName = DelayedRequestsFilename;
                    if (File.Exists(fileName))
                    {
                        string requests = File.ReadAllText(fileName);
                        DataReader reader = new DataReader(requests, SEPARATOR);
                        while (reader.HasNext())
                        {
                            mDelayedRequests.Add(reader.NextString());
                        }
                    }
                }
                return mDelayedRequests;
            }
        }

        private static void SaveDelayedRequests()
        {
            if (mDelayedRequests != null)
            {
                DataWriter writer = new DataWriter(SEPARATOR);
                for (int i = 0; i < mDelayedRequests.Count; ++i)
                {
                    writer.Add(mDelayedRequests[i]);
                }
                File.WriteAllText(DelayedRequestsFilename, writer.Data);
            }
        }

        private static bool SendDelayedRequests()
        {
            if (!mGate.Connected)
            {
                return false;
            }
            StringBuilder errLog = null;
            List<string> delayedRequests = DelayedRequests;
            while (delayedRequests.Count > 0)
            {
                try
                {
                    Gate.Request request = new Gate.Request(mGate, delayedRequests[0]);
                    request.Send();
                }
                catch (GameException exc)
                {
                    if (exc.IsRecoverable)
                    {
                        SaveDelayedRequests();
                        throw exc;
                    }

                    if (errLog == null)
                    {
                        errLog = new StringBuilder();
                    }
                    errLog.Append(exc.Message + "\n");
                }
                catch (Exception exc)
                {
                    if (errLog == null)
                    {
                        errLog = new StringBuilder();
                    }
                    errLog.Append(exc.Message + "\n");
                }
                delayedRequests.RemoveAt(0);
            }

            File.Delete(DelayedRequestsFilename);
            if (errLog != null)
            {
                throw new GameException(errLog.ToString());
            }
            return true;
        }

        public static void AddDelayedRequest(Gate.Request request)
        {
            DelayedRequests.Add(request.Data);
            SaveDelayedRequests();
        }

        public static Club Club
        {
            get { return mClub; }
        }
    }
}
